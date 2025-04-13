<?php

namespace Billing\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class UserSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $table = 'user_subscription';

    protected $fillable = [
        'user_id',
        'payment_gateway_transaction_id',
        'subscription_id',
        'status',
        'activated_at',
        'expired_at'
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\User\Entity\User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            PaymentGatewayTransaction::class,
            'payment_gateway_transaction_id'
        );
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE &&
            $this->expired_at > Carbon::now();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
            $this->expired_at <= Carbon::now();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('expired_at', '>', Carbon::now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function($q) {
            $q->where('status', self::STATUS_EXPIRED)
                ->orWhere('expired_at', '<=', Carbon::now());
        });
    }
}
