<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Models\StarInvoice;
use Illuminate\Support\Str;

class SubscriptionService
{
    /**
     * Конфигурация подписок: callback_data => [months, amount, title, description]
     */
    private const SUBSCRIPTION_CONFIG = [
        'subscribe_1_month' => [
            'months' => 1,
            'amount' => 1,
            'title' => 'VPN на 1 месяц',
            'description' => 'Доступ к VPN сервису на 1 месяц',
        ],
        'subscribe_3_months' => [
            'months' => 3,
            'amount' => 1,
            'title' => 'VPN на 3 месяца',
            'description' => 'Доступ к VPN сервису на 3 месяца',
        ],
        'subscribe_6_months' => [
            'months' => 6,
            'amount' => 1,
            'title' => 'VPN на 6 месяцев',
            'description' => 'Доступ к VPN сервису на 6 месяцев',
        ],
        'subscribe_1_year' => [
            'months' => 12,
            'amount' => 1,
            'title' => 'VPN на 1 год',
            'description' => 'Доступ к VPN сервису на 1 год',
        ],
    ];

    private TelegramApiService $telegramApiService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
    }

    public function getSubscriptionConfig(): array
    {
        return self::SUBSCRIPTION_CONFIG;
    }

    /**
     * Получить конфигурацию подписки по callback_data
     */
    public function getSubscriptionConfigByCallbackData(string $callbackData): ?array
    {
        return self::SUBSCRIPTION_CONFIG[$callbackData] ?? null;
    }

    /**
     * Проверить, является ли callback_data подпиской
     */
    public function isSubscriptionCallback(string $callbackData): bool
    {
        return isset(self::SUBSCRIPTION_CONFIG[$callbackData]);
    }

    /**
     * Обработать запрос на подписку
     */
    public function handleSubscription(int $chatId, string $callbackData, ?string $username = null): bool
    {
        $config = $this->getSubscriptionConfigByCallbackData($callbackData);

        if (!$config) {
            return false;
        }

        // Находим или создаём пользователя
        $user = User::firstOrCreate(
            ['telegram_id' => $chatId],
            ['telegram_username' => $username]
        );

        // Генерируем уникальный payload
        $payload = $this->generatePayload($user->id, $callbackData);

        // Создаём запись в star_invoices
        StarInvoice::create([
            'user_id' => $user->id,
            'telegram_username' => $username,
            'amount' => $config['amount'],
            'currency' => 'XTR',
            'status' => 'created',
            'payload' => $payload,
            'metadata' => [
                'subscription_type' => $callbackData,
                'months' => $config['months'],
            ],
        ]);

        // Отправляем инвойс в Telegram
        $response = $this->telegramApiService->sendInvoice(
            $chatId,
            $config['title'],
            $config['description'],
            $payload,
            $config['amount']
        );

        return $response && ($response['success'] ?? false);
    }

    /**
     * Генерация уникального payload для транзакции
     */
    private function generatePayload(int $userId, string $subscriptionType): string
    {
        $uniqueId = Str::random(8);
        return "user_{$userId}_{$subscriptionType}_{$uniqueId}";
    }
}
