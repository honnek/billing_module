<?php

namespace Billing\Service;

use Billing\Entity\PaymentGateway;
use Billing\Entity\PaymentGatewayTransaction;
use Billing\Entity\PaymentDetails;
use Billing\Exception\PaymentProcessingException;
use Billing\Gateway\ArkpayPaymentGateway;
use Billing\Gateway\CcbillPaymentGateway;
use Billing\Gateway\InstaxchangePaymentGateway;
use Billing\Gateway\MyxspendPaymentGateway;
use Billing\Gateway\PaymentGatewayInterface;
use Billing\Http\Request\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class PaymentService
{
    public function __construct(
        private readonly array $paymentGateways
    ) {}

    /**
     * Resolve payment gateway based on request
     */
    public function resolvePaymentGateway(Request $request, bool $forWebhook = false): array
    {
        $gatewayEntity = $forWebhook
            ? $this->resolveGatewayByWebhook($request->all())
            : $this->resolveGatewayByRequest($request);

        if (!$gatewayEntity) {
            throw new PaymentProcessingException('Payment gateway not identified', 404);
        }

        $gateway = $this->getGatewayImplementation($gatewayEntity);

        if (!$gateway) {
            throw new PaymentProcessingException('Payment gateway not supported', 404);
        }

        return [$gateway, $gatewayEntity];
    }

    public function createTransaction(PaymentDetails $paymentDetails): PaymentGatewayTransaction
    {
        return PaymentGatewayTransaction::create([
            'user_id' => $paymentDetails->getUser()->id,
            'payment_gateway_id' => $paymentDetails->getPaymentGateway()->id,
            'amount' => $paymentDetails->getAmount(),
            'status' => PaymentGatewayTransaction::STATUS_START,
            'metadata' => []
        ]);
    }

    public function getTransactionByHash(string $hash): ?PaymentGatewayTransaction
    {
        $salt = Config::get('services.payment_gateway.transaction_salt');

        return PaymentGatewayTransaction::whereRaw(
            "MD5(CONCAT(id, ?)) = ?",
            [$salt, $hash]
        )->first();
    }

    public function getTransactionByGatewayAndPayload(
        string $gatewayName,
        array $payload
    ): ?PaymentGatewayTransaction {
        $transactionId = $this->extractTransactionId($gatewayName, $payload);
        if (!$transactionId) {
            return null;
        }

        return $this->isHashedTransactionId($gatewayName)
            ? $this->getTransactionByHash($transactionId)
            : PaymentGatewayTransaction::find($transactionId);
    }

    public function getFailedCallbackUrl(): string
    {
        return $this->buildUrl(config('services.payment_gateway.after_callback_failed_url'));
    }

    public function getSuccessCallbackUrl(): string
    {
        return $this->buildUrl(config('services.payment_gateway.after_callback_success_url'));
    }

    private function getGatewayImplementation(PaymentGateway $paymentGateway): ?PaymentGatewayInterface
    {
        return $this->paymentGateways[$paymentGateway->name] ?? null;
    }

    private function resolveGatewayByRequest(PaymentRequest $request): ?PaymentGateway
    {
        foreach ($this->paymentGateways as $name => $gateway) {
            if ($name === $request->getPaymentGateway()) {
                return PaymentGateway::where('name', $name)
                    ->where('is_enabled', true)
                    ->first(['id', 'name']);
            }
        }

        return null;
    }

    private function resolveGatewayByWebhook(array $payload): ?PaymentGateway
    {
        foreach ($this->paymentGateways as $name => $gateway) {
            if ($gateway::canProcess($payload)) {
                return PaymentGateway::where('name', $name)
                    ->where('is_enabled', true)
                    ->first(['id', 'name']);
            }
        }

        return null;
    }

    private function extractTransactionId(string $gatewayName, array $payload): ?string
    {
        return match ($gatewayName) {
            ArkpayPaymentGateway::GATEWAY_NAME => $payload['mer'] ?? null,
            InstaxchangePaymentGateway::GATEWAY_NAME => $payload['ref'] ?? null,
            CcbillPaymentGateway::GATEWAY_NAME => $payload['X_id'] ?? null,
            MyxspendPaymentGateway::GATEWAY_NAME => $payload['cust_id'] ?? null,
            default => null
        };
    }

    private function isHashedTransactionId(string $gatewayName): bool
    {
        return $this->paymentGateways[$gatewayName]::USES_TRANSACTION_HASHES ?? false;
    }

    private function buildUrl(string $path): string
    {
        return rtrim(Config::get('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
