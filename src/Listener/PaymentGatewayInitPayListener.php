<?php

namespace Billing\Listener;

use Billing\Event\PaymentInitiatedEvent;
use Throwable;

class PaymentGatewayInitPayListener
{
    /**
     * Создание слушателя событий.
     */
    public function __construct()
    {
        // ...
    }

    /**
     * Обработка события PaymentInitiatedEvent.
     *
     * @param PaymentInitiatedEvent $event
     * @return void
     */
    public function handle(PaymentInitiatedEvent $event): void
    {
        // Доступ к полям с помощью `$event->paymentDetails` и `$event->metadata`
    }
}