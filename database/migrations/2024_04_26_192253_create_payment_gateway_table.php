<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Создает таблицу платежных шлюзов и устанавливает связи.
     */
    public function up(): void
    {
        // Создаем таблицу платежных шлюзов
        Schema::create('payment_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name', 255);
            $table->boolean('is_enabled')->default(true);
        });

        // Добавляем внешние ключи с проверкой существования таблиц
        if (Schema::hasTable('subscription')) {
            Schema::table('subscription', function (Blueprint $table) {
                $table->foreignId('payment_gateway_id')
                    ->constrained('payment_gateway')
                    ->cascadeOnDelete();
            });
        }
        if (Schema::hasTable('payment_transaction')) {
            Schema::table('payment_transaction', function (Blueprint $table) {
                $table->foreignId('payment_gateway_id')
                    ->constrained('payment_gateways')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Откат миграции с дополнительными проверками.
     */
    public function down(): void
    {
        // Удаляем связи только если они существуют
        Schema::table('subscription', function (Blueprint $table) {
            $table->dropForeignIfExists(['payment_gateway_id']);
        });

        Schema::table('payment_transaction', function (Blueprint $table) {
            $table->dropForeignIfExists(['payment_gateway_id']);
        });

        Schema::dropIfExists('payment_gateway');
    }
};