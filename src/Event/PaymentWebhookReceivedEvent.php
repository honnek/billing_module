<?php

namespace Billing\Event;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Billing\Entity\PaymentGatewayTransaction;

/**
 * Событие подтверждения оплаты
 */
class PaymentWebhookReceivedEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable,
        InteractsWithSockets,
        SerializesModels;

    /**
     * Создать экземпляр события PaymentGatewayWebhook.
     *
     * @param PaymentGatewayTransaction $paymentGatewayTransaction
     * @param Request $request
     */
    public function __construct(
        public PaymentGatewayTransaction $paymentGatewayTransaction,
        public Request $request
    ) {

    }
}
