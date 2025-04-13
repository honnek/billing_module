<?php

namespace Billing\Service;

use Billing\Http\Request\PaymentRequest;

class PaymentLogger
{
    /**
     * @param PaymentRequest $request
     * @return void
     */
    public function logPaymentRequest(PaymentRequest $request): void
    {
        $this->logInfo('Payment request', $request->validated());
    }

    /**
     * @param Request $request
     * @return void
     */
    public function logWebhookRequest(Request $request): void
    {
        $this->logInfo('Webhook received', $request->all());
    }

    /**
     * @param \Throwable $e
     * @param PaymentRequest $request
     * @return void
     */
    public function logPaymentError(\Throwable $e, PaymentRequest $request): void
    {
        $this->logError($e->getMessage(), [
            'exception' => get_class($e),
            'request' => $request->validated(),
        ]);
    }

    /**
     * @param \Throwable $e
     * @param Request $request
     * @return void
     */
    public function logWebhookError(\Throwable $e, Request $request): void
    {
        $this->logError($e->getMessage(), [
            'exception' => get_class($e),
            'webhook' => $request->all(),
        ]);
    }

    /**
     * @param string $message
     * @param array $data
     * @return void
     */
    public function logInfo(string $message, array $data = []): void
    {
        Log::channel('payment-webhook')->info($message, $data);
    }

    /**
     * @param string $message
     * @param array $data
     * @return void
     */
    public function logError(string $message, array $data = []): void
    {
        Log::channel('payment-webhook')->error($message, $data);
    }
}