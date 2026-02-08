<?php

namespace App\Handlers;

use Illuminate\Http\Request;
use App\Services\LocaleService;
use App\Services\AdminService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use App\Services\Telegram\TelegramKeyboardService;
use Illuminate\Validation\ValidationException;
use App\Services\Telegram\TelegramMessageHandlerService;

class MessageHandler
{
    private TelegramApiService $telegramApiService;
    private CacheService $cache;
    private LocaleService $localeService;
    private TelegramMessageHandlerService $telegramMessageHandlerService;
    private bool $isAdmin = false;
    
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->telegramMessageHandlerService = new TelegramMessageHandlerService();
        $this->cache = new CacheService();
        $this->localeService = new LocaleService();
    }
    
    public function handle(Request $request): void
    {
        $update = $request->all();
        Log::info('MessageHandler update', [
            'update' => $update
        ]);
        $message = $update['message'] ?? null;
        
        if (!$message) {
            throw new ValidationException('Invalid message data');
        }
        
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;
        $username = $message['from']['username'] ?? null;

        if (!$chatId) {
            Log::warning('Missing chatId or text in message', [
                'chatId' => $chatId,
                'text' => $text,
                'update' => $update
            ]);
            throw new ValidationException('Invalid message data');
        }

        if (!$text) {
            return;
        }

        $buttonHandler = TelegramKeyboardService::BUTTON_HANDLERS[$text] ?? null;
        if ($buttonHandler) {
            $handler = $buttonHandler['handler'];
            $this->telegramMessageHandlerService->$handler($chatId);
            return;
        }

        $this->isAdmin = intval(env('ADMIN_CHAT_ID')) === intval($chatId);

        $this->handleCachedActions($chatId, $text, $username);
    }

    private function handleCachedActions(int $chatId, string $text, ?string $username): void
    {
        if ($this->isAdmin) {
            $cachekey = $chatId . ':message_to_all';
            if ($this->cache->has($cachekey)) {
                $this->telegramMessageHandlerService->handleMessageToAllSend($text);
                $this->cache->forget($cachekey);
                return;
            }

            $cachekey = $chatId . ':message_to_user';
            if ($this->cache->has($cachekey)) {
                $targetUsername = $this->cache->get($cachekey);

                if ($targetUsername === 'start') {
                    $this->telegramMessageHandlerService->handleMessageToUserSaveUsername($text);
                    return;
                }

                $this->telegramMessageHandlerService->handleMessageToUserSendMessage($text);
                $this->cache->forget($cachekey);
                return;
            }
        }

        $cachekey = $chatId . ':support';
        if ($this->cache->has($cachekey)) {
            $message = 'Сообщение от пользователя ' . $username . ': ' . $text;
            (new AdminService())->sendMesssageToAdmin($message);

            $this->cache->forget($cachekey);
            $confirmationMessage = $this->localeService->get('support.confirmation');
            $this->telegramApiService->sendMessageToChat($confirmationMessage);
        }
    }
}
