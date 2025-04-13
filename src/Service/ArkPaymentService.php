<?php

namespace Billing\Service;

use Billing\Entity\PaymentGatewayTransaction;
use Billing\Entity\PaymentDetails;
use Billing\Exception\InvalidSignatureException;

class ArkPaymentService
{
    private const SIGNATURE_ALGORITHM = 'sha256';
    private const DESCRIPTION = 'Payment for subscription';
    private const HANDLE_PAYMENT = false;

    /**
     * @param string $httpMethod
     * @param string $uri
     * @param string $body
     * @param string $secretKey
     * @return string
     */
    public function createSignature(
        string $httpMethod,
        string $uri,
        string $body,
        string $secretKey
    ): string
    {
        return hash_hmac(
            self::SIGNATURE_ALGORITHM,
            sprintf("%s %s\n%s", $httpMethod, $uri, $body),
            $secretKey
        );
    }

    /**
     * @param string $httpMethod
     * @param string $uri
     * @param string $body
     * @param string $secretKey
     * @param string $signature
     * @return bool
     */
    public function isSignatureValid(
        string $httpMethod,
        string $uri,
        string $body,
        string $secretKey,
        string $signature
    ): bool
    {
        return $this->createSignature($httpMethod, $uri, $body, $secretKey) === $signature;
    }

    /**
     * @param PaymentGatewayTransaction $transaction
     * @param array $body
     * @return bool
     */
    public function verifyTransactionConsistency(
        PaymentGatewayTransaction $transaction,
        array                     $body
    ): bool
    {
        $requiredFields = [
            'externalCustomerId' => (string)$transaction->user_id,
            'amount' => $transaction->amount,
            'mid' => $transaction->metadata['merchant_id'] ?? null
        ];

        foreach ($requiredFields as $field => $expectedValue) {
            if (!isset($body[$field]) || (string)$body[$field] !== (string)$expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param PaymentDetails $paymentDetails
     * @param PaymentGatewayTransaction $transaction
     * @return array
     */
    public function buildPaymentPayload(
        PaymentDetails            $paymentDetails,
        PaymentGatewayTransaction $transaction
    ): array
    {
        return [
            'merchantTransactionId' => (string)$transaction->id,
            'amount' => $paymentDetails->getAmount(),
            'currency' => $paymentDetails->getCurrency()->getCode(),
            'description' => self::DESCRIPTION,
            'externalCustomerId' => (string)$paymentDetails->getUser()->id,
            'handlePayment' => self::HANDLE_PAYMENT,
            'returnUrl' => config('app.url') . '/api/v1/payment/callback',
        ];
    }
}
