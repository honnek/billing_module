<?php

namespace Billing\Service;

use Billing\Entity\PaymentGatewayTransaction;
use Billing\Entity\PaymentGatewayTransactionLog;
use RuntimeException;

class PaymentTransactionLogService
{
    /**
     * @param PaymentGatewayTransaction $transaction
     * @param int $statusCode
     * @param string $message
     * @return void
     * @throws \JsonException
     */
    public function createLog(
        PaymentGatewayTransaction $transaction,
        int $statusCode,
        string $message
    ): void
    {
        PaymentGatewayTransactionLog::create([
            'transaction_id' => $transaction->id,
            'log' => json_encode(
                ['statusCode' => $statusCode, 'message' => $message],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            ),
        ]);
    }
}
