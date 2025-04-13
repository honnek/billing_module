<?php

namespace Billing\Attributes;

use App\Dto\Attributes\AbstractAttributesObject;

class SubscriptionPaymentAttributesVo extends AbstractAttributesObject
{
    /**
     * Returns available attribute names
     *
     * @return array
     */
    public static function attributes(): array
    {
        return [
            'amount',
            'currency',
            'paymentGateway',
        ];
    }
}