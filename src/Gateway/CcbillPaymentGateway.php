<?php

namespace Billing\Gateway;

use Billing\{Entity\PaymentDetails,
    Entity\PaymentGatewayTransaction,
    Entity\Subscription,
    Entity\User,
    Exception\PaymentProcessingException,
    Service\CcbillPaymentService,
    Service\PaymentLogger,
    Service\PaymentService};
use Symfony\Component\HttpKernel\Exception\HttpException;

class CcbillPaymentGateway implements PaymentGatewayInterface
{
    public const GATEWAY_NAME = 'ccbill';
    public const NEEDS_PAYMENT_REDIRECT = true;
    public const USES_TRANSACTION_HASHES = false;

    public function __construct(
        private readonly CcbillPaymentService $paymentService,
        private readonly PaymentService $transactionService,
        private readonly PaymentLogger $paymentLogger,
    ) {}

    public function validateRequest(\Illuminate\Http\Request $request, array $payload): bool
    {
        $userId = $payload['X-promo_id'] ?? $payload['promo_id'] ?? null;

        return $userId
            ? User::where('id', $userId)->exists()
            : isset($payload['subscriptionId']);
    }

    public function createPaymentRequest(
        PaymentDetails $details,
        PaymentGatewayTransaction $transaction
    ): \Psr\Http\Message\RequestInterface {
        return new \GuzzleHttp\Psr7\Request('GET', '/');
    }

    public function processPayment(PaymentDetails $paymentDetails): PaymentGatewayTransaction
    {
        $configKey = $paymentDetails->getPlan() === 'premium' ? 'premium' : 'pro';
        $formId = config("services.cashier.plans.prod_ccbill_{$configKey}.form_id");
        $price = config("services.cashier.plans.prod_ccbill_{$configKey}.price");

        $paymentDetails->setAmount($price);
        $transaction = $this->transactionService->createTransaction($paymentDetails);
        $transaction->update(['status' => PaymentGatewayTransaction::STATUS_PENDING]);

        $baseUrl = $paymentDetails->isTest()
            ? config('services.payment_gateway.ccbill.api_url_test')
            : config('services.payment_gateway.ccbill.api_url');

        $paymentDetails->setRedirectToPayUrl(sprintf(
            '%s%s?promo_id=%d&sales_id=%d',
            $baseUrl,
            $formId,
            $paymentDetails->getUser()->id,
            $transaction->id
        ));

        return $transaction;
    }

    public function handleTransactionCallback(array $payload): PaymentGatewayTransaction
    {
        $transaction = $this->getTransactionByPayload($payload['eventType'], $payload)
            ?? throw new HttpException(400, 'Transaction not found');

        match ($payload['eventType']) {
            'NewSaleSuccess', 'RenewalSuccess' => $this->handleSuccessfulTransaction($transaction, $payload),
            'Refund', 'Chargeback', 'Return' => $this->markTransactionRefunded($transaction),
            'Expiration', 'RenewalFailure' => $this->markTransactionCancelled($transaction),
            default => $this->paymentLogger->logInfo(self::GATEWAY_NAME.' NEUTRAL WEBHOOK', $payload)
        };

        return $transaction;
    }

    public function resolveSubscription(PaymentGatewayTransaction $transaction): ?Subscription
    {
        $planType = str_contains(strtolower($transaction->metadata['plan_name'] ?? ''), 'premium')
            ? 'premium'
            : 'pro';

        return Subscription::query()
            ->where('title', 'like', "%{$planType}%")
            ->where('payment_gateway_id', $transaction->payment_gateway_id)
            ->first();
    }

    public static function canProcess(array $payload): bool
    {
        return isset($payload['eventType']);
    }

    private function handleSuccessfulTransaction(PaymentGatewayTransaction $transaction, array $payload): void
    {
        if (!$this->paymentService->validateTransaction($transaction, $payload)) {
            throw new HttpException(400, 'Transaction not valid');
        }

        $updateData = [
            'status' => PaymentGatewayTransaction::STATUS_SUCCESS,
            'metadata' => [
                'subscription_id' => $payload['subscriptionId'],
                'plan_name' => $payload['formName'],
                'recurring_period' => $payload['recurringPeriod'],
            ]
        ];

        if (isset($payload['subscriptionInitialPrice'])) {
            $updateData['sum'] = (float)$payload['subscriptionInitialPrice'];
        }

        if ($payload['eventType'] === 'RenewalSuccess') {
            $this->createRenewalTransaction($transaction);
        }

        $transaction->update($updateData);
        $this->paymentLogger->logInfo(self::GATEWAY_NAME.' POSITIVE WEBHOOK', $payload);
    }

    private function createRenewalTransaction(PaymentGatewayTransaction $original): PaymentGatewayTransaction
    {
        $original->update(['status' => PaymentGatewayTransaction::STATUS_CANCELLED]);
        return $original->replicate()->fill([
            'status' => PaymentGatewayTransaction::STATUS_SUCCESS
        ])->save();
    }

    private function getTransactionByPayload(string $eventType, array $payload): ?PaymentGatewayTransaction
    {
        $transactionId = $payload['sales_id'] ?? $payload['X-sales_id'] ?? null;

        return $transactionId
            ? $this->transactionService->findActiveTransaction($transactionId)
            : $this->transactionService->findTransactionBySubscriptionId($payload['subscriptionId']);
    }

    private function markTransactionRefunded(PaymentGatewayTransaction $transaction): void
    {
        $transaction->update([
            'status' => PaymentGatewayTransaction::STATUS_REFUNDED
        ]);
        $this->paymentLogger->logInfo(
            self::GATEWAY_NAME.' NEGATIVE WEBHOOK',
            ['transaction_id' => $transaction->id, 'status' => 'refunded']
        );
    }

    private function markTransactionCancelled(PaymentGatewayTransaction $transaction): void
    {
        $transaction->update([
            'status' => PaymentGatewayTransaction::STATUS_CANCELLED
        ]);
        $this->paymentLogger->logInfo(
            self::GATEWAY_NAME.' NEGATIVE WEBHOOK',
            ['transaction_id' => $transaction->id, 'status' => 'cancelled']
        );
    }
}
