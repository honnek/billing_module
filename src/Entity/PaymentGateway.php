<?php

namespace Billing\Entity;

use Illuminate\Database\Eloquent\Model;
use Billing\Gateway\ArkpayPaymentGateway;
use Billing\Gateway\CcbillPaymentGateway;
use Billing\Gateway\InstaxchangePaymentGateway;
use Billing\Gateway\MyxspendPaymentGateway;

class PaymentGateway extends Model
{
    public const GATEWAY_CCBILL = 'ccbill';
    public const GATEWAY_INSTAXCHANGE = 'instaxchange';
    public const GATEWAY_ARKPAY = 'arkpay';
    public const GATEWAY_MYXSPEND = 'myxspend';

    public const GATEWAY_CLASS_MAP = [
        self::GATEWAY_CCBILL => CcbillPaymentGateway::class,
        self::GATEWAY_INSTAXCHANGE => InstaxchangePaymentGateway::class,
        self::GATEWAY_ARKPAY => ArkpayPaymentGateway::class,
        self::GATEWAY_MYXSPEND => MyxspendPaymentGateway::class,
    ];

    protected $table = 'payment_gateway'; // Лучше использовать множественное число для таблиц
    public $timestamps = false;
    protected $fillable = [
        'name',
        'is_enabled',
    ];

    /**
     * @return string|null
     */
    public function getGatewayClass(): ?string
    {
        return self::GATEWAY_CLASS_MAP[$this->name] ?? null;
    }
}
