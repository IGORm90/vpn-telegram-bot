<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use App\Services\Telegram\TelegramMessageHandlerService;
use App\Services\Telegram\TelegramKeyboardService;

class MainController extends Controller
{
    private TelegramApiService $telegramApiService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
    }

    /**
     * Обработчик webhook запросов от Telegram
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        try {
            $update = $request->all();
            
            Log::info('Telegram webhook received', ['update' => $update]);

            // Проверяем наличие callback_query в обновлении
            if (isset($update['callback_query'])) {
                (new TelegramMessageHandlerService())->handleCallbackQuery($update['callback_query']);

                return response()->json(['ok' => true]);
            }

            // Проверяем наличие сообщения в обновлении
            if (!isset($update['message'])) {
                return response()->json(['ok' => true]);
            }

            $message = $update['message'];
            
            // Извлекаем данные сообщения
            $chatId = $message['chat']['id'] ?? null;
            $text = $message['text'] ?? null;

            // Если нет текста или chat_id, игнорируем
            if (!$chatId || !$text) {
                return response()->json(['ok' => true]);
            }

            if ($text === '/start') {
                (new TelegramMessageHandlerService())->handleStartMessage($chatId);
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Telegram webhook handler error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}

