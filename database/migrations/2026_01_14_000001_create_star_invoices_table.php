<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('star_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users');
            $table->string('telegram_username')->nullable();
            $table->integer('amount'); // Количество звезд
            $table->string('currency', 10)->default('XTR'); // Валюта (XTR для Telegram Stars)

            $table->enum('status', ['created', 'confirmed', 'complited'])->default('created');
            $table->string('payload')->unique(); // Уникальный идентификатор транзакции (user_123_order_abc)
            $table->string('telegram_payment_charge_id')->nullable()->unique(); // ID транзакции от Telegram
            $table->string('provider_payment_charge_id')->nullable()->unique(); // ID транзакции от Telegram

            $table->json('metadata')->nullable(); // Дополнительные данные (детали платежа, ошибки и т.д.)

            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('star_invoices');
    }
};
