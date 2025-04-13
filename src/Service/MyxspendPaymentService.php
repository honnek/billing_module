<?php

namespace Billing\Service;

use Billing\Entity\PaymentDetails;
use Billing\Entity\PaymentGatewayTransaction;

class MyxspendPaymentService
{
    /**
     * @param PaymentService $paymentService
     */
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * @param PaymentGatewayTransaction $transaction
     * @param PaymentDetails $paymentDetails
     * @return array
     */
    public function createBodyForPay(
        PaymentGatewayTransaction $transaction,
        PaymentDetails $paymentDetails
    ): array {
        return [
            'customerOrderId' => $this->paymentService->createHashTransactionId($transaction),
            'amount'          => $paymentDetails->getAmount(),
            'currency'        => $paymentDetails->getCurrency()?->getCode(),
            'email'           => $paymentDetails->getUser()->email,
            'firstName'       => $paymentDetails->getFirstname(),
            'lastName'        => $paymentDetails->getLastName(),
            'phoneNo'         => $paymentDetails->getPhone(),
        ];
    }
}
