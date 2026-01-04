<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Основной контроллер для обработки Telegram webhook
 */
class MainController extends Controller
{
    private TelegramService $telegramService;

    /**
     * MainController конструктор
     */
    public function __construct()
    {
        $this->telegramService = new TelegramService();
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

            // Отправляем то же сообщение обратно пользователю (эхо)
            $this->telegramService->sendMessageToChat($chatId, $text);

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

