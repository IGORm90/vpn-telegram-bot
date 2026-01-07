<?php

namespace App\Services\Telegram;

use App\Services\UserService;
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

        // Получаем массив клавиатуры
        $keyboardArray = (new TelegramKeyboardService())->getKeyboardArray();
        
        if (isset($keyboardArray[$callbackData])) {
            $textToSend = $keyboardArray[$callbackData];
        } else {
            $textToSend = 'Неизвестная команда';
        }

        if ($callbackData === 'connect_vpn') {
            $configString = $this->userService->createUserConfig($chatId, $username);
            if (!$configString) {
                $this->telegramApiService->sendMessageToChat($chatId, 'Failed to create user config');
                return false;
            }

            $this->telegramApiService->sendMessageToChat($chatId, $configString);
            return true;
        }
        
        Log::info('Keyboard Array:', ['keyboardArray' => $keyboardArray]);
        Log::info('Callback Data:', ['callbackData' => $callbackData]);

        $this->telegramApiService->sendMessageToChat($chatId, $textToSend);

        return true;
    }
    
}