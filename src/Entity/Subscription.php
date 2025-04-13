<?php

namespace Billing\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Subscription extends Model
{
    protected $table = 'subscription'; // Приведено к множественному числу

    protected $fillable = [
        'title',
        'price',
        'duration_days',
        'credits_amount',
        'payment_gateway_id',
        'is_active'
    ];

    protected $casts = [
        'price' => 'float',
        'duration_days' => 'integer',
        'credits_amount' => 'integer',
        'is_active' => 'boolean',
    ];

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function getDurationInDays(): int
    {
        return (int) $this->duration_days;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
