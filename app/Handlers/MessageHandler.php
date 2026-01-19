<?php

namespace App\Handlers;

use Illuminate\Http\Request;
use App\Services\TextService;
use App\Services\UserService;
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
    private TextService $textService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->cache = new CacheService();
        $this->textService = new TextService();
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
        if (!$chatId || !$text) {
            Log::warning('Missing chatId or text in message', [
                'chatId' => $chatId,
                'text' => $text,
                'update' => $update
            ]);
            throw new ValidationException('Invalid message data');
        }

        Log::info('Processing message', [
            'chatId' => $chatId,
            'text' => $text,
            'username' => $username
        ]);

        if ($text === '/start') {
            Log::info('Handling /start command', ['chatId' => $chatId]);
            try {
                (new TelegramMessageHandlerService())->handleStartMessage($chatId);
                Log::info('/start command handled successfully', ['chatId' => $chatId]);
            } catch (\Exception $e) {
                Log::error('Error handling /start command', [
                    'chatId' => $chatId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            return;
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
