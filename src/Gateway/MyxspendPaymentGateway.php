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
    Service\MyxspendPaymentService,
    Service\PaymentService,
    Service\PaymentTransactionLogService
};
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MyxspendPaymentGateway implements PaymentGatewayInterface
{
    public const GATEWAY_NAME = 'myxspend';
    public const NEEDS_PAYMENT_REDIRECT = true;
    public const NEEDS_POST_CALLBACK_REDIRECT = true;
    public const USES_TRANSACTION_HASHES = true;

    private ?string $authToken = null;
    private Client $httpClient;

    public function __construct(
        private readonly MyxspendPaymentService $paymentService,
        private readonly PaymentTransactionLogService $logService,
        private readonly PaymentService $transactionService,
    ) {
        $this->httpClient = new Client([
            'timeout' => 10,
            'allow_redirects' => false,
        ]);
    }

    public function processPayment(PaymentDetails $paymentDetails): PaymentGatewayTransaction
    {
        $this->authenticate();
        $transaction = $this->transactionService->createTransaction($paymentDetails);

        try {
            $response = $this->httpClient->send(
                $this->createPaymentRequest($paymentDetails, $transaction)
            );
            $responseData = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== Response::HTTP_OK || empty($responseData['PaymentLink'])) {
                $this->handleFailedPayment($transaction, $response);
                throw new PaymentProcessingException(
                    $responseData['message'] ?? 'Payment processing failed',
                    $response->getStatusCode()
                );
            }

            $transaction->update(['status' => PaymentGatewayTransaction::STATUS_PENDING]);
            $paymentDetails->setRedirectToPayUrl($responseData['PaymentLink']);

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

        if (!$this->authToken) {
            throw new \RuntimeException('Authentication required');
        }

        return new \GuzzleHttp\Psr7\Request(
            'POST',
            config('services.payment_gateway.myxspend.api_url') . '/v1/payment/process',
            [
                'X-API-KEY' => config('services.payment_gateway.myxspend.key'),
                'X-COMPANY-ID' => config('services.payment_gateway.myxspend.company_id'),
                'Authorization' => 'Bearer ' . $this->authToken,
                'Content-Type' => 'application/json',
            ],
            $body
        );
    }

    public function handleTransactionCallback(array $payload): PaymentGatewayTransaction
    {
        $transaction = $this->transactionService->getTransactionByHash(
            $payload['customerOrderId'] ?? null
        ) ?? throw new HttpException(400, 'Transaction not found');

        match (true) {
            strtolower($transaction->status) !== PaymentGatewayTransaction::STATUS_PENDING
            => throw new HttpException(400, 'Payment not pending'),
            in_array($payload['status'], ['EXPIRED', 'REFUNDED', 'UNDERPAID', 'FAILED'])
            => $this->markTransactionFailed($transaction),
            $payload['status'] === 'SUCCESSFUL'
            => $transaction->update(['status' => PaymentGatewayTransaction::STATUS_SUCCESS]),
            default => null
        };

        return $transaction;
    }

    public function validateRequest(\Illuminate\Http\Request $request, array $payload): bool
    {
        return isset($payload['customerOrderId']) &&
            $this->transactionService->getTransactionByHash($payload['customerOrderId']);
    }

    public function resolveSubscription(PaymentGatewayTransaction $transaction): ?Subscription
    {
        return Subscription::query()
            ->where('title', 'like', '%myxspend%')
            ->where('payment_gateway_id', $transaction->payment_gateway_id)
            ->first();
    }

    public static function canProcess(array $payload): bool
    {
        return isset($payload['customerOrderId'], $payload['status'], $payload['dateTime']);
    }

    private function authenticate(): void
    {
        try {
            $response = $this->httpClient->send(
                new \GuzzleHttp\Psr7\Request(
                    'POST',
                    config('services.payment_gateway.myxspend.api_url') . '/v1/auth/login',
                    [
                        'Content-Type' => 'application/json',
                    ],
                    json_encode([
                        'email' => config('services.payment_gateway.myxspend.email'),
                        'password' => config('services.payment_gateway.myxspend.password'),
                    ])
                )
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $this->authToken = $data['token'] ?? null;

            if (!$this->authToken) {
                throw new PaymentProcessingException('Authentication failed', 401);
            }

        } catch (GuzzleException $e) {
            throw new PaymentProcessingException(
                'Authentication error: ' . $e->getMessage(),
                500,
                $e
            );
        }
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