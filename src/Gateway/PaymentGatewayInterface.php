<?php

namespace Billing\Gateway;

use Illuminate\Http\Request;
use Psr\Http\Message\RequestInterface;
use Billing\Entity\PaymentGatewayTransaction;
use Billing\Entity\Subscription;
use Billing\Entity\PaymentDetails;

interface PaymentGatewayInterface
{
    public const NEEDS_PAYMENT_REDIRECT = false;
    public const NEEDS_POST_CALLBACK_REDIRECT = false;
    public const USES_TRANSACTION_HASHES = false;

    /**
     * Проверит валидность запроса.
     */
    public function validateRequest(Request $request, array $payload): bool;

    /**
     * Создает Request для создания транзакции на стороне платежного шлюза.
     */
    public function createPaymentRequest(
        PaymentDetails $paymentDetails,
        PaymentGatewayTransaction $transaction
    ): RequestInterface;

    /**
     * Если не выбрасываюстся Exception,
     * то создан PaymentGatewayTransaction и ожидает подтверждения.
     *
     * @throws PaymentProcessingException
     */
    public function processPayment(PaymentDetails $paymentDetails): PaymentGatewayTransaction;

    /**
     * Обрабатывает gateway transaction callback
     */
    public function handleTransactionCallback(array $payload): PaymentGatewayTransaction;

    /**
     * Извлекает подписку, связанную с платежной транзакцией
     */
    public function resolveSubscription(PaymentGatewayTransaction $transaction): ?Subscription;
}
