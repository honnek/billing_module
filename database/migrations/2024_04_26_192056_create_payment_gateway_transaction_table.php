<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создает таблицу транзакций платежных шлюзов.
     */
    public function up(): void
    {
        Schema::create('gateway_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->constrained();
            $table->decimal('amount', 12, 2);
            /** @todo вынести в энам */
            $table->enum('state', ['pending', 'completed', 'failed']);
            $table->timestamps();
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateway_transaction');
    }
};
