<?php

namespace Billing\Gateway;

use GuzzleHttp\{Client, Exception\GuzzleException};
use Psr\Http\Message\RequestInterface;
use Billing\{
    Entity\PaymentDetails,
    Entity\PaymentGatewayTransaction,
    Entity\Subscription,
    Exception\PaymentProcessingException,
    Service\ArkPaymentService,
    Service\PaymentService,
    Service\PaymentTransactionLogService
};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ArkpayPaymentGateway implements PaymentGatewayInterface
{
    public const GATEWAY_NAME = 'arkpay';
    public const USES_TRANSACTION_HASHES = false;

    private Client $httpClient;

    public function __construct(
        private readonly ArkPaymentService $paymentService,
        private readonly PaymentTransactionLogService $logService,
        private readonly PaymentService $transactionService,
    ) {
        $this->httpClient = new Client([
            'timeout' => 5,
            'allow_redirects' => false,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function processPayment(PaymentDetails $paymentDetails): PaymentGatewayTransaction
    {
        $transaction = $this->transactionService->createTransaction($paymentDetails);

        try {
            $response = $this->httpClient->send(
                $this->createPaymentRequest($paymentDetails, $transaction)
            );

            if ($response->getStatusCode() !== Response::HTTP_CREATED) {
                $this->handleFailedPayment($transaction, $response);
                throw new PaymentProcessingException($response->getReasonPhrase(), $response->getStatusCode());
            }

            $transaction->update(['status' => PaymentGatewayTransaction::STATUS_PENDING]);
            return $transaction;

        } catch (GuzzleException $e) {
            $this->logService->createLog($transaction, $e->getCode(), $e->getMessage());
            throw new PaymentProcessingException('Payment gateway communication error', 500, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function createPaymentRequest(
        PaymentDetails $details,
        PaymentGatewayTransaction $transaction
    ): RequestInterface {
        $payload = $this->paymentService->buildPaymentPayload($details, $transaction);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: throw new \RuntimeException('Invalid payment payload');

        return new Request(
            'POST',
            config('services.payment_gateway.arkpay.api_url') . '/merchant/api/transactions',
            [
                'x-api-key' => config('services.payment_gateway.arkpay.key'),
                'signature' => $this->paymentService->createSignature(
                    'POST',
                    '/api/v1/merchant/api/transactions',
                    $body,
                    config('services.payment_gateway.arkpay.secret')
                ),
                'Content-Type' => 'application/json',
            ],
            $body
        );
    }

    /**
     * @inheritDoc
     */
    public function handleTransactionCallback(array $payload): PaymentGatewayTransaction
    {
        $transaction = $this->transactionService->getTransactionByGatewayAndPayload(
            self::GATEWAY_NAME,
            $payload
        ) ?? throw new HttpException(400, 'Transaction not found');

        match (true) {
            $transaction->status !== PaymentGatewayTransaction::STATUS_PENDING
            => throw new HttpException(400, 'Payment not pending'),
            !$this->paymentService->verifyTransactionConsistency($transaction, $payload)
            => throw new HttpException(400, 'Transaction not valid'),
            in_array($payload['status'], ['FAILED', 'REFUNDED'])
            => $this->markTransactionFailed($transaction),
            $payload['status'] === 'COMPLETED'
            => $transaction->update(['status' => PaymentGatewayTransaction::STATUS_SUCCESS]),
            default => null
        };

        return $transaction;
    }

    /**
     * @inheritDoc
     */
    public function validateRequest(\Illuminate\Http\Request $request, array $payload): bool
    {
        return $this->paymentService->isSignatureValid(
            $request->method(),
            $request->getUri(),
            json_encode($payload),
            config('services.payment_gateway.arkpay.secret'),
            $request->header('signature')
        );
    }

    public function resolveSubscription(PaymentGatewayTransaction $transaction): ?Subscription
    {
        return null; // Not implemented yet
    }

    public static function canProcess(array $payload): bool
    {
        return isset($payload['merchantTransactionId'], $payload['externalCustomerId'], $payload['amount']);
    }

    private function handleFailedPayment(PaymentGatewayTransaction $transaction, $response): void
    {
        $this->logService->createLog(
            $transaction,
            $response->getStatusCode(),
            $response->getReasonPhrase()
        );

        $transaction->update([
            'status' => PaymentGatewayTransaction::STATUS_FAILURE_ON_START
        ]);
    }

    private function markTransactionFailed(PaymentGatewayTransaction $transaction): void
    {
        $transaction->update([
            'status' => PaymentGatewayTransaction::STATUS_FAILURE_ON_FINISH
        ]);
        throw new HttpException(400, 'Payment finished failure');
    }
}
