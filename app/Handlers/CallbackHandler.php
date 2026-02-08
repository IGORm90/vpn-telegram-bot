<?php

namespace App\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use App\Services\Telegram\SubscriptionService;
use App\Services\Telegram\TelegramMessageHandlerService;
use App\Services\LocaleService;

class CallbackHandler
{
    private TelegramApiService $telegramApiService;
    private SubscriptionService $subscriptionService;
    private TelegramMessageHandlerService $telegramMessageHandlerService;
    private LocaleService $localeService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->subscriptionService = new SubscriptionService();
        $this->telegramMessageHandlerService = new TelegramMessageHandlerService();
        $this->localeService = new LocaleService();
    }

    public function handle(Request $request): void
    {
        $update = $request->all();

        $callbackQuery = $update['callback_query'] ?? null;

        if (!$callbackQuery) {
            Log::warning('Missing callback_query in update', ['update' => $update]);
            return;
        }

        $callbackQueryId = $callbackQuery['id'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $callbackData = $callbackQuery['data'] ?? null;

        // Отвечаем на callback query, чтобы убрать "часики" на кнопке
        if ($callbackQueryId) {
            $this->telegramApiService->answerCallbackQuery($callbackQueryId);
        }

        if (!$chatId || !$callbackData) {
            Log::warning('Missing chatId or callbackData', [
                'chatId' => $chatId,
                'callbackData' => $callbackData,
            ]);
            return;
        }

        if ($this->isVpnServerCallback($callbackData)) {

            $serverId = (int) explode('_', $callbackData)[1];
            $this->telegramMessageHandlerService->handleConnectVpn($serverId);
            return;
        }

        // Обработка пополнения баланса (покупка звёзд)
        if ($this->subscriptionService->isSubscriptionCallback($callbackData)) {
            $this->handleSubscriptionCallback($callbackData);
            return;
        }

        // Обработка активации подписки (списание баланса)
        if ($this->subscriptionService->isActivationCallback($callbackData)) {
            $this->handleActivationCallback($callbackData);
            return;
        }

        Log::warning('Unknown callback_data', ['callback_data' => $callbackData]);
    }

    /**
     * Обработка callback для подписок
     */
    private function handleSubscriptionCallback(string $callbackData): void
    {
        $success = $this->subscriptionService->handleSubscription($callbackData);

        if (!$success) {
            $this->telegramApiService->sendErrorMessage();
        }
    }

    private function isVpnServerCallback(string $callbackData): bool {
        return str_starts_with($callbackData, 'server_');
    }

    /**
     * Обработка callback для активации подписки
     */
    private function handleActivationCallback(string $callbackData): void
    {
        $result = $this->subscriptionService->handleActivation($callbackData);

        if ($result['success']) {
            $message = $this->localeService->get('subscription.activation_success', [
                '{months}' => $result['months'],
                '{expires_at}' => $result['new_expires_at']->format('d.m.Y'),
                '{balance}' => $result['new_balance'],
            ]);
            $this->telegramApiService->sendMessageToChat($message);
        } else {
            if ($result['error'] === 'insufficient_balance') {
                $message = $this->localeService->get('subscription.insufficient_balance', [
                    '{required}' => $result['required'],
                    '{current}' => $result['current'],
                ]);
                $this->telegramApiService->sendMessageToChat($message);
            } else {
                $this->telegramApiService->sendErrorMessage();
            }
        }
    }
}
