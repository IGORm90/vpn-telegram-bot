<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\AdminService;
use App\Services\CacheService;
use App\Services\TextService;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use App\Services\Telegram\TelegramMessageHandlerService;

class MainController extends Controller
{
    private TelegramApiService $telegramApiService;
    private CacheService $cache;
    private TextService $textService;
    
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->cache = new CacheService();
        $this->textService = new TextService();
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

        if ($text === 'Подключить vpn') {
            $this->handleConnectVpn($chatId, $username);
            return;
        }
        
        if ($text === 'Написать в поддержку') {
            $this->handleSupport($chatId);
            return;
        }

        $cachekey = $chatId . ':support';
        if ($this->cache->has($cachekey)) {
            $text = 'Сообщение от пользователя ' . $username . ': ' . $text;
            (new AdminService())->sendMesssageToAdmin($text);

            $this->cache->forget($cachekey);
            $confirmationMessage = $this->textService->get('support.confirmation');
            $this->telegramApiService->sendMessageToChat($chatId, $confirmationMessage);
        }
    }

        
    private function handleConnectVpn(int $chatId, string $username): void
    {
        $userService = new UserService();
        $configString = $userService->createUserConfig($chatId, $username);
        if (!$configString) {
            $errorMessage = $this->textService->get('errors.config_creation_failed');
            $this->telegramApiService->sendMessageToChat($chatId, $errorMessage);
            return;
        }
        $this->telegramApiService->sendMessageToChat($chatId, $configString);
    }

    private function handleSupport(int $chatId): void
    {
        $cache = new CacheService();
        $cachekey = $chatId . ':support';
        $cache->set($cachekey, $chatId, 600);
        
        $promptMessage = $this->textService->get('support.prompt');
        $this->telegramApiService->sendMessageToChat($chatId, $promptMessage);
    }
}

