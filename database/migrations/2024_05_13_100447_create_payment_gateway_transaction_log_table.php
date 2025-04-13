<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Создает таблицу логов транзакций платежного шлюза.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('payment_transaction_log', function (Blueprint $table) {
            $table->id();

            // Внешний ключ с каскадным удалением
            $table->foreignId('payment_transaction_id')
                ->constrained('payment_gateway_transaction')
                ->cascadeOnDelete();

            $table->json('log_data')->nullable();
            $table->text('raw_log')->nullable();

            $table->string('log_level', 20)->default('info');
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('payment_transaction_id');
        });
    }

    /**
     * Откатывает миграцию.
     *
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasTable('payment_transaction_log')) {
            Schema::dropIfExists('payment_transaction_log');
        }
    }
};
