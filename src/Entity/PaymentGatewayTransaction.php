<?php

namespace Billing\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayTransaction extends Model
{
    public const STATUS_START = 'start';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE_ON_START = 'failure_on_start';
    public const STATUS_FAILURE_ON_FINISH = 'failure_on_finish';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'payment_gateway_transaction';

    protected $fillable = [
        'user_id',
        'payment_gateway_id',
        'amount',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\User\Entity\User::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
