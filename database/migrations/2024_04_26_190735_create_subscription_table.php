<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class() extends Migration
{
    /**
     * Активирует миграцию - создает таблицу подписок.
     */
    public function up(): void
    {
        Schema::create('user_subscription', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('duration_days');
            $table->foreignId('payment_gateway_id')->constrained();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Откатывает миграцию.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
