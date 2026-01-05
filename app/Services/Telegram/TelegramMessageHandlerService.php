<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Log;


class TelegramMessageHandlerService
{

    const HELLO_MESSAGE = 'Привет! Я бот для управления вашим VPN. Чтобы начать, нажмите на кнопку ниже.';
    private TelegramApiService $telegramApiService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
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
    public function handleCallbackQuery(array $callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $callbackData = $callbackQuery['data'] ?? null;

        if (!$chatId || !$callbackData) {
            return response()->json(['ok' => true]);
        }

        // Получаем массив клавиатуры
        $keyboardArray = (new TelegramKeyboardService())->getKeyboardArray();
        
        if (isset($keyboardArray[$callbackData])) {
            $textToSend = $keyboardArray[$callbackData];
        } else {
            $textToSend = 'Неизвестная команда';
        }
        
        Log::info('Keyboard Array:', ['keyboardArray' => $keyboardArray]);
        Log::info('Callback Data:', ['callbackData' => $callbackData]);

        $this->telegramApiService->sendMessageToChat($chatId, $textToSend);

        return true;
    }
    
}