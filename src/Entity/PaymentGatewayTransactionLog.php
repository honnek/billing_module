<?php

namespace Billing\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayTransactionLog extends Model
{
    protected $table = 'payment_gateway_transaction_log';

    protected $fillable = [
        'transaction_id',
        'log_data',
        'status_code',
        'ip_address',
    ];

    protected $casts = [
        'log_data' => 'array', // Логи могут быть структурированными
        'status_code' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            PaymentGatewayTransaction::class,
            'transaction_id'
        );
    }
}