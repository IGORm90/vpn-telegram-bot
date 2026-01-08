<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AdminService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use App\Services\Telegram\TelegramMessageHandlerService;

class MainController extends Controller
{
    private TelegramApiService $telegramApiService;
    private CacheService $cache;
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->cache = new CacheService();
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
            $username = $message['from']['username'] ?? null;
            
            // Извлекаем данные сообщения
            $chatId = $message['chat']['id'] ?? null;
            $text = $message['text'] ?? null;

            // Если нет текста или chat_id, игнорируем
            if (!$chatId || !$text) {
                return response()->json(['ok' => true]);
            }

            $this->handleMessage($chatId, $text, $username);

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Telegram webhook handler error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function handleMessage(int $chatId, string $text, string $username): void
    {
        if ($text === '/start') {
            (new TelegramMessageHandlerService())->handleStartMessage($chatId);
        }

        $cachekey = $chatId . ':support';
        if ($this->cache->has($cachekey)) {
            $text = 'Сообщение от пользователя ' . $username . ': ' . $text;
            (new AdminService())->sendMesssageToAdmin($text);

            $this->cache->forget($cachekey);
            $this->telegramApiService->sendMessageToChat($chatId, 'Сообщение отправлено в поддержку');
        }
    }
}

