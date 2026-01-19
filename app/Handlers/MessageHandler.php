<?php

namespace App\Handlers;

use Illuminate\Http\Request;
use App\Services\LocaleService;
use App\Services\AdminService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
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
    
    public function handle(Request $request)
    {
        $update = $request->all();
        Log::info('Update', $update);
        $message = $update['message'] ?? null;
        // Проверяем наличие сообщения в обновлении
        if (!$message) {
            throw new ValidationException('Invalid message data');
        }
        
        // Извлекаем данные сообщения
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;
        $username = $message['from']['username'] ?? null;

        // Если нет текста или chat_id, игнорируем
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

        if ($text === '/start') {
            $this->telegramMessageHandlerService->handleStartMessage($chatId);
            return;
        }
        if ($text === 'Главная') {
            $this->telegramMessageHandlerService->handleMainPanel($chatId);
            return;
        }
        if ($text === 'Подключить vpn') {
            $this->telegramMessageHandlerService->handleConnectVpn($chatId, $username);
            return;
        }
        if ($text === 'Написать в поддержку') {
            $this->telegramMessageHandlerService->handleSupport($chatId);
            return;
        }
        if ($text === 'Подписка') {
            $this->telegramMessageHandlerService->handleBalance($chatId);
            return;
        }
        if ($text === 'Оплата доступа') {
            $this->telegramMessageHandlerService->handleBalance($chatId);
            return;
        }
        if ($text === 'Админ панель') {
            $this->telegramMessageHandlerService->handleAdminPanel($chatId);
            return;
        }
        if ($text === 'Написать пользователю') {
            $this->telegramMessageHandlerService->handleMessageToUserStart($chatId);
            return;
        }
        if ($text === 'Написать всем') {
            $this->telegramMessageHandlerService->handleMessageToAllStart($chatId);
            return;
        }

        $this->isAdmin = intval(env('ADMIN_CHAT_ID')) === intval($chatId);

        if ($this->isAdmin) {
            $cachekey = $chatId . ':message_to_all';
            if ($this->cache->has($cachekey)) {
                $this->telegramMessageHandlerService->handleMessageToAllSend($text);
                $this->cache->forget($cachekey);
                return;
            }

            $cachekey = $chatId . ':message_to_user';
            if ($this->cache->has($cachekey)) {
                $username = $this->cache->get($cachekey);

                if ($username === 'start') {
                    $this->telegramMessageHandlerService->handleMessageToUserSaveUsername($chatId, $text);
                    return;
                }

                $this->telegramMessageHandlerService->handleMessageToUserSendMessage($chatId, $text);
                $this->cache->forget($cachekey);
                return;
            }
        }

        $cachekey = $chatId . ':support';
        if ($this->cache->has($cachekey)) {
            $text = 'Сообщение от пользователя ' . $username . ': ' . $text;
            (new AdminService())->sendMesssageToAdmin($text);

            $this->cache->forget($cachekey);
            $confirmationMessage = $this->localeService->get('support.confirmation');
            $this->telegramApiService->sendMessageToChat($chatId, $confirmationMessage);
            return;
        }
    }
}
