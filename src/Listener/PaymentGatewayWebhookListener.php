<?php

namespace Billing\Listener;

use Billing\Event\PaymentWebhookReceivedEvent;
use Billing\Service\PaymentLogger;
use Throwable;

class PaymentGatewayWebhookListener
{
    public function __construct(
        private readonly PaymentLogger           $paymentLogger,
    ) {
    }

    /**
     * Обработка события PaymentWebhookReceivedEvent.
     *
     * @param PaymentWebhookReceivedEvent $event
     * @return void
     * @throws Throwable
     */
    public function handle(PaymentWebhookReceivedEvent $event): void
    {
        //.. TODO обработка платежа
    }

    /**
     * Обработать провал задания.
     */
    public function failed(PaymentWebhookReceivedEvent $event, Throwable $exception): void
    {
        // ...
        $this->paymentLogger->logError(
            $exception->getMessage(),
            $event->paymentGatewayTransaction->toArray()
        );
    }
}
