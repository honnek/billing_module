<?php

namespace Billing\Adapter;

use Illuminate\Http\{Request, RedirectResponse};
use Psr\Http\Message\RequestInterface;
use Billing\{Entity\PaymentDetails,
    Entity\PaymentGatewayTransaction,
    Entity\Subscription,
    Event\PaymentInitiatedEvent,
    Event\PaymentWebhookReceivedEvent,
    Gateway\PaymentGatewayInterface
};

class PaymentGatewayAdapter implements PaymentGatewayInterface
{
    public function __construct(
        private PaymentGatewayInterface $gatewayImplementation
    )
    {
    }

    public function processPayment(PaymentDetails $paymentDetails): PaymentGatewayTransaction
    {
        return $this->gatewayImplementation->processPayment($paymentDetails);
    }

    public function handleTransactionCallback(array $payload): PaymentGatewayTransaction
    {
        return $this->gatewayImplementation->handleTransactionCallback($payload);
    }

    public function createPaymentRequest(
        PaymentDetails            $paymentDetails,
        PaymentGatewayTransaction $transaction
    ): RequestInterface
    {
        return $this->gatewayImplementation->createPaymentRequest($paymentDetails, $transaction);
    }

    public function validateRequest(Request $request, array $payload): bool
    {
        return $this->gatewayImplementation->validateRequest($request, $payload);
    }

    public function notifyPaymentInitiation(PaymentDetails $paymentDetails, array $metadata = []): void
    {
        PaymentInitiatedEvent::dispatch($paymentDetails, $metadata);
    }

    public function notifyWebhookReceived(
        PaymentGatewayTransaction $transaction,
        Request                   $request
    ): void
    {
        PaymentWebhookReceivedEvent::dispatch($transaction, $request);
    }

    public function resolveSubscription(PaymentGatewayTransaction $transaction): ?Subscription
    {
        return $this->gatewayImplementation->resolveSubscription($transaction);
    }

    public function requiresPaymentRedirect(): bool
    {
        return $this->gatewayImplementation::NEEDS_PAYMENT_REDIRECT;
    }

    public function requiresPostCallbackRedirect(): bool
    {
        return $this->gatewayImplementation::NEEDS_POST_CALLBACK_REDIRECT;
    }

    public function usesTransactionHashes(): bool
    {
        return $this->gatewayImplementation::USES_TRANSACTION_HASHES;
    }
}
