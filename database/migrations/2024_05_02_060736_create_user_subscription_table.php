<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Создает таблицу подписок пользователей.
     */
    public function up(): void
    {
        Schema::create('user_subscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->constrained('payment_gateway_transaction')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription')->cascadeOnDelete();

            /** @todo вынести в энам */
            $table->enum('subscription_status', ['active', 'pending', 'expired', 'canceled']);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'subscription_status']);
        });
    }

    /**
     * Откат миграции.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscription');
    }
};

