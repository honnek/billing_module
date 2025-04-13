<?php

namespace Billing\Event;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Billing\Entity\PaymentDetails;

/**
 * Событие инициализации оплаты
 */
class PaymentInitiatedEvent
{
    use Dispatchable,
        InteractsWithSockets,
        SerializesModels;

    /**
     * Создать экземпляр события PaymentGatewayInitPay.
     *
     * @param PaymentDetails $paymentDetails
     * @param array $metadata
     */
    public function __construct(
        PaymentDetails $paymentDetails,
        array          $metadata,
    )
    {
    }
}
