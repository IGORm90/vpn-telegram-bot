<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Models\StarInvoice;
use Illuminate\Support\Str;
use App\Entities\UserEntity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Конфигурация пополнения баланса: callback_data => [amount (звёзды), balance_amount, title, description]
     * Маппинг звёзд на баланс:
     * 100 звёзд -> 182 баланса
     * 250 звёзд -> 429 баланса
     * 500 звёзд -> 849 баланса
     * 1000 звёзд -> 1679 баланса
     */
    private const SUBSCRIPTION_CONFIG = [
        'subscribe_1_month' => [
            'amount' => 100,
            'balance_amount' => 182,
            'title' => 'Пополнение на 182 ₽',
            'description' => 'Пополнение баланса на 182 ₽',
        ],
        'subscribe_3_months' => [
            'amount' => 250,
            'balance_amount' => 429,
            'title' => 'Пополнение на 429 ₽',
            'description' => 'Пополнение баланса на 429 ₽',
        ],
        'subscribe_6_months' => [
            'amount' => 500,
            'balance_amount' => 849,
            'title' => 'Пополнение на 849 ₽',
            'description' => 'Пополнение баланса на 849 ₽',
        ],
        'subscribe_1_year' => [
            'amount' => 1000,
            'balance_amount' => 1679,
            'title' => 'Пополнение на 1679 ₽',
            'description' => 'Пополнение баланса на 1679 ₽',
        ],
    ];

    /**
     * Конфигурация активации подписки: callback_data => [balance_cost, months, title]
     * Маппинг баланса на месяцы:
     * 182 баланса -> 1 месяц
     * 429 баланса -> 3 месяца
     * 849 баланса -> 6 месяцев
     * 1679 баланса -> 12 месяцев
     */
    private const ACTIVATION_CONFIG = [
        'activate_1_month' => [
            'balance_cost' => 182,
            'months' => 1,
            'title' => '1 месяц — 182 ₽',
        ],
        'activate_3_months' => [
            'balance_cost' => 429,
            'months' => 3,
            'title' => '3 месяца — 429 ₽',
        ],
        'activate_6_months' => [
            'balance_cost' => 849,
            'months' => 6,
            'title' => '6 месяцев — 849 ₽',
        ],
        'activate_1_year' => [
            'balance_cost' => 1679,
            'months' => 12,
            'title' => '1 год — 1679 ₽',
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
    public function handleSubscription(string $callbackData): bool
    {
        $userEntity = UserEntity::getInstance();
        $config = $this->getSubscriptionConfigByCallbackData($callbackData);

        Log::info('handleSubscription started', [
            'callback_data' => $callbackData,
            'telegram_id' => $userEntity->telegramId,
            'config' => $config,
        ]);

        if (!$config) {
            Log::warning('handleSubscription: config not found', ['callback_data' => $callbackData]);
            return false;
        }

        try {
            // Находим или создаём пользователя
            $user = User::firstOrCreate(
                ['telegram_id' => $userEntity->telegramId],
                ['telegram_username' => $userEntity->telegramUsername]
            );

            // Генерируем уникальный payload
            $payload = $this->generatePayload($user->id, $callbackData);

            Log::info('handleSubscription: creating invoice', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'payload' => $payload,
                'amount' => $config['amount'],
            ]);

            // Создаём запись в star_invoices
            StarInvoice::create([
                'user_id' => $user->id,
                'telegram_username' => $user->telegram_username,
                'amount' => $config['amount'],
                'currency' => 'XTR',
                'status' => 'created',
                'payload' => $payload,
                'metadata' => [
                    'subscription_type' => $callbackData,
                    'balance_amount' => $config['balance_amount'],
                ],
            ]);

            // Отправляем инвойс в Telegram (используем telegram_id, а не внутренний id!)
            $response = $this->telegramApiService->sendInvoice(
                $user->telegram_id,
                $config['title'],
                $config['description'],
                $payload,
                $config['amount']
            );

            Log::info('handleSubscription: sendInvoice response', [
                'response' => $response,
            ]);

            return $response && ($response['success'] ?? false);
        } catch (\Exception $e) {
            Log::error('handleSubscription: exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Генерация уникального payload для транзакции
     */
    private function generatePayload(int $userId, string $subscriptionType): string
    {
        $uniqueId = Str::random(8);
        return "user_{$userId}_{$subscriptionType}_{$uniqueId}";
    }

    /**
     * Получить конфигурацию активации подписки
     */
    public function getActivationConfig(): array
    {
        return self::ACTIVATION_CONFIG;
    }

    /**
     * Получить конфигурацию активации по callback_data
     */
    public function getActivationConfigByCallbackData(string $callbackData): ?array
    {
        return self::ACTIVATION_CONFIG[$callbackData] ?? null;
    }

    /**
     * Проверить, является ли callback_data активацией подписки
     */
    public function isActivationCallback(string $callbackData): bool
    {
        return isset(self::ACTIVATION_CONFIG[$callbackData]);
    }

    /**
     * Активировать подписку (списать баланс и продлить expires_at)
     */
    public function handleActivation(string $callbackData): array
    {
        $userEntity = UserEntity::getInstance();
        $config = $this->getActivationConfigByCallbackData($callbackData);

        if (!$config) {
            return ['success' => false, 'error' => 'invalid_config'];
        }

        $user = User::where('telegram_id', $userEntity->telegramId)->first();

        if (!$user) {
            return ['success' => false, 'error' => 'user_not_found'];
        }

        $balanceCost = $config['balance_cost'];
        $months = $config['months'];

        // Проверяем достаточно ли баланса
        if ($user->balance < $balanceCost) {
            return [
                'success' => false,
                'error' => 'insufficient_balance',
                'required' => $balanceCost,
                'current' => $user->balance,
            ];
        }

        // Списываем баланс и продлеваем подписку
        $oldBalance = $user->balance;
        $currentExpiresAt = $user->expires_at;

        $baseDate = ($currentExpiresAt && $currentExpiresAt->isFuture())
            ? $currentExpiresAt->copy()
            : Carbon::now();

        $newExpiresAt = $baseDate->addMonths($months);

        $user->update([
            'balance' => $oldBalance - $balanceCost,
            'expires_at' => $newExpiresAt,
        ]);

        Log::info('Subscription activated', [
            'user_id' => $user->id,
            'months' => $months,
            'balance_cost' => $balanceCost,
            'old_balance' => $oldBalance,
            'new_balance' => $oldBalance - $balanceCost,
            'old_expires_at' => $currentExpiresAt?->toDateTimeString(),
            'new_expires_at' => $newExpiresAt->toDateTimeString(),
        ]);

        return [
            'success' => true,
            'months' => $months,
            'new_expires_at' => $newExpiresAt,
            'new_balance' => $oldBalance - $balanceCost,
        ];
    }
}
