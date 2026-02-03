<?php

namespace App\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use App\Services\Telegram\SubscriptionService;
use App\Services\Telegram\TelegramMessageHandlerService;

class CallbackHandler
{
    private TelegramApiService $telegramApiService;
    private SubscriptionService $subscriptionService;
    private TelegramMessageHandlerService $telegramMessageHandlerService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->subscriptionService = new SubscriptionService();
        $this->telegramMessageHandlerService = new TelegramMessageHandlerService();
    }

    public function handle(Request $request): void
    {
        $update = $request->all();

        Log::info('CallbackHandler update', ['update' => $update]);

        $callbackQuery = $update['callback_query'] ?? null;

        if (!$callbackQuery) {
            Log::warning('Missing callback_query in update', ['update' => $update]);
            return;
        }

        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $callbackData = $callbackQuery['data'] ?? null;
        $username = $callbackQuery['from']['username'] ?? null;

        if (!$chatId || !$callbackData) {
            Log::warning('Missing chatId or callbackData', [
                'chatId' => $chatId,
                'callbackData' => $callbackData,
            ]);
            return;
        }

        if ($this->isVpnServerCallback($callbackData)) {

            $serverId = explode('_', $callbackData)[1];
            $this->telegramMessageHandlerService->handleConnectVpn($chatId, $serverId, $username);
            return;
        }

        // Обработка подписок
        if ($this->subscriptionService->isSubscriptionCallback($callbackData)) {
            $this->handleSubscriptionCallback($chatId, $callbackData, $username);
            return;
        }

        Log::warning('Unknown callback_data', ['callback_data' => $callbackData]);
    }

    /**
     * Обработка callback для подписок
     */
    private function handleSubscriptionCallback(int $chatId, string $callbackData, ?string $username): void
    {
        $success = $this->subscriptionService->handleSubscription($chatId, $callbackData, $username);

        if (!$success) {
            $this->telegramApiService->sendErrorMessage($chatId);
        }
    }

    private function isVpnServerCallback(string $callbackData): bool {
        return str_starts_with($callbackData, 'server_');
    }
}
