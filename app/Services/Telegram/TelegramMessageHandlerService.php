<?php

namespace App\Services\Telegram;

use App\Services\UserService;
use App\Services\AdminService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;


class TelegramMessageHandlerService
{

    const HELLO_MESSAGE = 'Привет! Я бот для управления вашим VPN. Чтобы начать, нажмите на кнопку ниже.';
    private TelegramApiService $telegramApiService;
    private UserService $userService;
    
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->userService = new UserService();
    }

    public function handleStartMessage(int $chatId): void
    {
        $options = [
            'reply_markup' => (new TelegramKeyboardService())->getKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, self::HELLO_MESSAGE, $options);
    }

        /**
     * Обработка callback query от inline клавиатуры
     *
     * @param array $callbackQuery
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallbackQuery(array $callbackQuery): bool
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $callbackData = $callbackQuery['data'] ?? null;
        $username = $callbackQuery['from']['username'] ?? null;
        if (!$chatId || !$callbackData || !$username) {
            Log::error('Callback query is invalid', ['callbackQuery' => $callbackQuery]);
            return true;
        }
        
        switch ($callbackData) {
            case 'connect_vpn':
                $this->handleConnectVpn($chatId, $username);
                break; 
            case 'support':
                $this->handleSupport($chatId);
                break;
            // case 'pay':
            //     $this->handlePay($chatId, $username);
            //     break;
            // case 'balance':
            //     $this->handleBalance($chatId, $username);
            //     break;
            default:
                $this->telegramApiService->sendMessageToChat($chatId, 'Неизвестная команда');
                break;
        }

        return true;
    }
    
    private function handleConnectVpn(int $chatId, string $username): void
    {
        $configString = $this->userService->createUserConfig($chatId, $username);
        if (!$configString) {
            $this->telegramApiService->sendMessageToChat($chatId, 'Failed to create user config');
            return;
        }
        $this->telegramApiService->sendMessageToChat($chatId, $configString);
    }

    private function handleSupport(int $chatId): void
    {
        $cache = new CacheService();
        $cachekey = $chatId . ':support';
        $cache->set($cachekey, $chatId, 600);
        $this->telegramApiService->sendMessageToChat($chatId, 'Напишите ваш вопрос в чат:');
    }
}