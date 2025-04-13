<?php

namespace Billing\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\RequestInterface;
use Billing\{
    Entity\PaymentDetails,
    Entity\PaymentGatewayTransaction,
    Entity\Subscription,
    Exception\PaymentProcessingException,
    Service\InstaxchangePaymentService,
    Service\PaymentService,
    Service\PaymentTransactionLogService
};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InstaxchangePaymentGateway implements PaymentGatewayInterface
{
    public const GATEWAY_NAME = 'instaxchange';
    public const USES_TRANSACTION_HASHES = false;

    private Client $httpClient;

    public function __construct(
        private readonly InstaxchangePaymentService $paymentService,
        private readonly PaymentTransactionLogService $logService,
        private readonly PaymentService $transactionService,
    ) {
        $this->httpClient = new Client([
            'timeout' => 5,
            'allow_redirects' => false,
        ]);
    }

    public function processPayment(PaymentDetails $paymentDetails): PaymentGatewayTransaction
    {
        $transaction = $this->transactionService->createTransaction($paymentDetails);

        try {
            $response = $this->httpClient->send(
                $this->createPaymentRequest($paymentDetails, $transaction)
            );

            if ($response->getStatusCode() !== Response::HTTP_CREATED) {
                $this->handleFailedPayment($transaction, $response);
                throw new PaymentProcessingException(
                    $response->getReasonPhrase(),
                    $response->getStatusCode()
                );
            }

            $transaction->update(['status' => PaymentGatewayTransaction::STATUS_PENDING]);
            return $transaction;

        } catch (GuzzleException $e) {
            $this->logService->logTransactionError(
                $transaction,
                $e->getCode(),
                $e->getMessage()
            );
            throw new PaymentProcessingException('Payment gateway communication error', 500, $e);
        }
    }

    public function createPaymentRequest(
        PaymentDetails $details,
        PaymentGatewayTransaction $transaction
    ): RequestInterface {
        $payload = $this->paymentService->buildPaymentPayload($details, $transaction);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES) ?:
            throw new \RuntimeException('Invalid payment payload');

        return new \GuzzleHttp\Psr7\Request(
            'POST',
            config('services.payment_gateway.instaxchange.api_url') . '/api/session',
            [
                'Content-Type' => 'application/json',
            ],
            $body
        );
    }

    public function handleTransactionCallback(array $payload): PaymentGatewayTransaction
    {
        $transaction = $this->transactionService->getTransactionByGatewayAndPayload(
            self::GATEWAY_NAME,
            $payload
        ) ?? throw new HttpException(400, 'Transaction not found');

        match (true) {
            $transaction->status !== PaymentGatewayTransaction::STATUS_PENDING
            => throw new HttpException(400, 'Payment not pending'),
            !$this->paymentService->validateTransaction($transaction, $payload)
            => throw new HttpException(400, 'Transaction not valid'),
            in_array($payload['data']['status'], ['failed', 'refunded'])
            => $this->markTransactionFailed($transaction),
            $payload['data']['status'] === 'completed'
            => $transaction->update(['status' => PaymentGatewayTransaction::STATUS_SUCCESS]),
            default => null
        };

        return $transaction;
    }

    public function validateRequest(\Illuminate\Http\Request $request, array $payload): bool
    {
        return $this->paymentService->isValidInstxKey(
            $payload,
            $request->header('X-Instaxwh-Key')
        );
    }

    public function resolveSubscription(PaymentGatewayTransaction $transaction): ?Subscription
    {
        return null; // Not implemented yet
    }

    public static function canProcess(array $payload): bool
    {
        return isset($payload['reference'], $payload['data']);
    }

    private function handleFailedPayment(PaymentGatewayTransaction $transaction, $response): void
    {
        $this->logService->logTransactionError(
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
