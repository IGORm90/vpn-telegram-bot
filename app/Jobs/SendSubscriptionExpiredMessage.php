<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramApiService;
use Illuminate\Support\Facades\Log;

class SendSubscriptionExpiredMessage extends Job
{
    /**
     * Количество попыток выполнения задачи
     */
    public int $tries = 3;

    /**
     * @param int $telegramId Telegram ID получателя
     */
    public function __construct(
        private int $telegramId
    ) {}

    /**
     * Отправить сообщение пользователю об истечении подписки
     */
    public function handle(): void
    {
        $telegramApiService = new TelegramApiService();

        $message = "⏰ Ваша подписка истекла.\n\nVPN был отключён. Для продления подписки воспользуйтесь меню бота.";

        try {
            $response = $telegramApiService->sendMessageToChat($message, [], $this->telegramId);

            if (!$response || !$response['success']) {
                Log::warning('Subscription expired message delivery failed', [
                    'telegram_id' => $this->telegramId,
                    'error' => $response['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Subscription expired message exception', [
                'telegram_id' => $this->telegramId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
