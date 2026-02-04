<?php

namespace App\Services\Telegram;

use App\Entities\UserEntity;
use App\Services\HttpService;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Service для взаимодействия с Telegram Bot API
 */
class TelegramApiService
{
    const UNKNOWN_ERROR = 'Unknown error';
    const ERROR_MESSAGE = 'Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже или напишите в поддержку.';

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
    public function sendMessageToChat(string $text, array $options = [], $chatId = null): ?array
    {
        $chatId = $chatId ?? UserEntity::getUserTelegramId();
        if (!$chatId) {
            return null;
        }

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
                'error' => $response['message'] ?? self::UNKNOWN_ERROR,
            ]);
        }

        return $response;
    }

    /**
     * Отправка запроса в чат
     *
     * @param string|int $chatId
     * @param array $options
     * @return array|null
     */
    public function sendRequestToChat($chatId, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
        ], $options);

        $response = $this->httpService->post($this->baseUrl . 'sendMessage', $data);

        if (!($response && $response['success'])){
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $response['message'] ?? self::UNKNOWN_ERROR,
            ]);
        }

        return $response;
    }

    /**
     * Отправка фото с текстом в указанный чат
     *
     * @param string|int $chatId
     * @param string $filePath Путь к файлу на диске
     * @param string $caption Текст под картинкой (опционально)
     * @param array $options Дополнительные опции (parse_mode, reply_markup и т.д.)
     * @return array|null
     */
    public function sendMessageToChatWithPhoto($chatId, string $filePath, string $caption = '', array $options = []): ?array
    {
        if (!file_exists($filePath)) {
            Log::error('Photo file not found', ['file_path' => $filePath]);
            return [
                'success' => false,
                'message' => 'File not found',
                'data' => null,
            ];
        }

        // Формируем multipart данные
        $multipart = [
            [
                'name' => 'chat_id',
                'contents' => (string)$chatId,
            ],
            [
                'name' => 'photo',
                'contents' => file_get_contents($filePath),
                'filename' => basename($filePath),
            ],
        ];

        // Добавляем caption если указан
        if (!empty($caption)) {
            $multipart[] = [
                'name' => 'caption',
                'contents' => $caption,
            ];
        }

        // Добавляем дополнительные опции (parse_mode, reply_markup и т.д.)
        foreach ($options as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => is_array($value) ? json_encode($value) : (string)$value,
            ];
        }

        try {
            $response = $this->httpService->postMultipart($this->baseUrl . 'sendPhoto', $multipart);

            if (!($response && $response['success'])) {
                Log::error('Failed to send Telegram photo', [
                    'chat_id' => $chatId,
                    'file_path' => $filePath,
                    'error' => $response['message'] ?? self::UNKNOWN_ERROR,
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Exception while sending Telegram photo', [
                'chat_id' => $chatId,
                'file_path' => $filePath,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
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
     * Отправка инвойса для оплаты Звездами
     *
     * @param string|int $chatId
     * @param string $title
     * @param string $description
     * @param string $payload
     * @param int $amount
     * @param array $options
     * @return array|null
     */
    public function sendInvoice($chatId, string $title, string $description, string $payload, int $amount, array $options = []): ?array
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => '', // Для звезд всегда пустая строка
            'currency' => 'XTR',
            'prices' => json_encode([
                ['label' => 'Цена', 'amount' => $amount]
            ]),
        ], $options);

        $response = $this->httpService->post($this->baseUrl . 'sendInvoice', $data);

        if ($response && $response['success']) {
            Log::info('Telegram invoice sent', [
                'chat_id' => $chatId,
                'amount' => $amount,
                'payload' => $payload,
            ]);
        } else {
            Log::error('Failed to send Telegram invoice', [
                'chat_id' => $chatId,
                'error' => $response['message'] ?? self::UNKNOWN_ERROR,
            ]);
        }

        return $response;
    }

    /**
     * Ответ на PreCheckoutQuery
     *
     * @param string $preCheckoutQueryId
     * @param bool $ok
     * @param string|null $errorMessage
     * @return array|null
     */
    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok = true, ?string $errorMessage = null): ?array
    {
        $data = [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok,
        ];

        if (!$ok && $errorMessage) {
            $data['error_message'] = $errorMessage;
        }

        $response = $this->httpService->post($this->baseUrl . 'answerPreCheckoutQuery', $data);

        if ($response && $response['success']) {
            Log::info('PreCheckoutQuery answered', [
                'query_id' => $preCheckoutQueryId,
                'ok' => $ok,
            ]);
        } else {
            Log::error('Failed to answer PreCheckoutQuery', [
                'query_id' => $preCheckoutQueryId,
                'error' => $response['message'] ?? self::UNKNOWN_ERROR,
            ]);
        }

        return $response;
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

    public function sendErrorMessage(string $errorMessage = ''): void
    {
        if (!$errorMessage) {
            $errorMessage = self::ERROR_MESSAGE;
        }

        $this->sendMessageToChat($errorMessage);
    }
}

