<?php

namespace Billing\Service;

use Billing\Entity\PaymentDetails;
use Billing\Entity\PaymentGatewayTransaction;

class InstaxchangePaymentService
{
    const AMOUNT_DIRECTION = 'sending';

    /**
     * @param array $body
     * @return string
     * @throws \JsonException
     */
    public function createInstaxwhKey(array $body): string
    {
        $secret = config('services.payment_gateway.instaxchange.secretKey');
        ksort($body);
        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return md5($jsonBody . ':' . $secret);
    }

    /**
     * @param array $body
     * @param string $instaxwhKey
     * @return bool
     * @throws \JsonException
     */
    public function isValidInstxKey(array $body, string $instaxwhKey): bool
    {
        return $this->createInstaxwhKey($body) === $instaxwhKey;
    }

    /**
     * @param PaymentDetails $paymentDetails
     * @param PaymentGatewayTransaction $transaction
     * @return array
     */
    public function createBodyForPay(
        PaymentDetails $paymentDetails,
        PaymentGatewayTransaction $transaction
    ): array {
        return [
            'accountRefId'   => config('services.payment_gateway.instaxchange.account_ref_id'),
            'toAmount'       => $paymentDetails->getAmount(),
            'toCurrency'     => $paymentDetails->getCurrency()->getCode(),
            'amountDirection'=> self::AMOUNT_DIRECTION,
            'webhookRef'     => (string)$transaction->id,
        ];
    }
}
