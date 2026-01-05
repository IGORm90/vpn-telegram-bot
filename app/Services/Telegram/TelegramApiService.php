<?php

namespace App\Services\Telegram;

use App\Services\HttpService;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Service для взаимодействия с Telegram Bot API
 */
class TelegramApiService
{
    private HttpService $httpService;
    private string $botToken;
    private string $baseUrl;

    /**
     * @param string $botToken
     * @param string|int $chatId
     */
    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}/";
        $this->httpService = new HttpService([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Отправка сообщения в указанный чат
     *
     * @param string|int $chatId
     * @param string $text
     * @param array $options
     * @return array|null
     */
    public function sendMessageToChat($chatId, string $text, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options);

        $response = $this->httpService->post($this->baseUrl . 'sendMessage', $data);

        if ($response && $response['success']) {
            Log::info('Telegram message sent', [
                'chat_id' => $chatId,
                'message_id' => $response['data']['result']['message_id'] ?? null,
            ]);
        } else {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $response['message'] ?? 'Unknown error',
            ]);
        }

        return $response;
    }

    /**
     * Получить информацию о боте
     *
     * @return array|null
     */
    public function getMe(): ?array
    {
        return $this->httpService->get($this->baseUrl . 'getMe');
    }

    /**
     * Получить базовый URL API
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}

