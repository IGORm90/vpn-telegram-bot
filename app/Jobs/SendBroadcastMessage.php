<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramApiService;
use Illuminate\Support\Facades\Log;

class SendBroadcastMessage extends Job
{
    /**
     * Количество попыток выполнения задачи
     */
    public int $tries = 3;

    /**
     * @param string $message Текст сообщения
     * @param int $telegramId Telegram ID получателя
     */
    public function __construct(
        private string $message,
        private int $telegramId
    ) {}

    /**
     * Отправить сообщение пользователю
     */
    public function handle(): void
    {
        $telegramApiService = new TelegramApiService();

        try {
            $response = $telegramApiService->sendMessageToChat($this->message, [], $this->telegramId);

            if (!$response || !$response['success']) {
                Log::warning('Broadcast message delivery failed', [
                    'telegram_id' => $this->telegramId,
                    'error' => $response['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Broadcast message exception', [
                'telegram_id' => $this->telegramId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
