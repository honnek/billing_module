<?php

namespace Billing\Service;

use Billing\Entity\PaymentGatewayTransaction;
use Billing\Exception\TransactionVerificationException;

class CcbillPaymentService
{
    /**
     * @param PaymentGatewayTransaction $transaction
     * @param array $body
     * @return bool
     */
    public function verifyTransactionConsistency(
        PaymentGatewayTransaction $transaction,
        array $body
    ): bool {
        if (isset($body['pr']) || isset($body['X-pr'])) {
            $promoId = $body['pr'] ?? $body['X-pr'];
            return $transaction->user_id === $promoId;
        }

        if (isset($body['subs'])) {
            return $this->verifySubscriptionMatch($body['subs']);
        }

        return false;
    }

    /**
     * @param string $subscriptionId
     * @return bool
     */
    private function verifySubscriptionMatch(string $subscriptionId): bool
    {
        return PaymentGatewayTransaction::query()
            ->where('metadata->subscription_id', $subscriptionId)
            ->where('status', '!=', PaymentGatewayTransaction::STATUS_CANCELLED)
            ->exists();
    }
}