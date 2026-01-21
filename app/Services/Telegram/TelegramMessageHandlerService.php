<?php

namespace App\Services\Telegram;

use App\Services\LocaleService;
use App\Services\UserService;
use App\Services\CacheService;
use App\Repositories\UserRepository;

class TelegramMessageHandlerService
{
    private TelegramApiService $telegramApiService;
    private LocaleService $localeService;
    private UserRepository $userRepository;
    
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->localeService = new LocaleService();
        $this->userRepository = new UserRepository();
    }

    public function handleStartMessage(int $chatId): void
    {
        $user = $this->userRepository->findByTelegramId($chatId);

        $keyboardService = new TelegramKeyboardService($user);
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        
        if (!$user) {
            $imagePath = __DIR__ . '/../../../resources/images/image.png';
            
            $message = $this->localeService->get('welcome.message');
            $this->telegramApiService->sendMessageToChatWithPhoto($chatId, $imagePath, $message, $options);

            $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('welcome.instruction'));
            $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('info.available_servers'));
        } else {
            $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('support.start'), $options);
            $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('info.available_servers'));
        }
    }

    public function handleMainPanel(int $chatId): void
    {
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, 'Главная', $options);
    }

    public function handleAdminPanel(int $chatId): void
    {
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getAdminKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, 'Админ панель', $options);
    }

    public function handleConnectVpn(int $chatId, string $username): void
    {
        $userService = new UserService();
        $configString = $userService->createUserConfig($chatId, $username);
        if (!$configString) {
            $errorMessage = $this->localeService->get('errors.config_creation_failed');
            $this->telegramApiService->sendMessageToChat($chatId, $errorMessage);
            return;
        }
        $this->telegramApiService->sendMessageToChat($chatId, $configString);
    }

    public function handleSupport(int $chatId): void
    {
        $cache = new CacheService();
        $cachekey = $chatId . ':support';
        $cache->set($cachekey, $chatId, 1200);
        
        $promptMessage = $this->localeService->get('support.prompt');
        $this->telegramApiService->sendMessageToChat($chatId, $promptMessage);
    }

    public function handleBalance(int $chatId): void
    {
        $user = $this->userRepository->findByTelegramId($chatId);
        if (!$user) {
            $this->telegramApiService->sendErrorMessage($chatId);
            return;
        }

        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getSubscriptionsKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('subscription.message', [
            '{expires_at}' => $user->expires_at->format('d.m.Y')
        ]), $options);
    }

    public function handleMessageToUserStart(int $chatId): void
    {
        $cache = new CacheService();
        $cachekey = $chatId . ':message_to_user';
        $cache->set($cachekey, 'start', 1200);

        $this->telegramApiService->sendMessageToChat($chatId, 'Напишите username пользователя');
    }

    public function handleMessageToUserSaveUsername(int $chatId, string $username): void
    {
        $user = $this->userRepository->getByTelegramUsername($username);
        if (!$user) {
            $this->telegramApiService->sendErrorMessage($chatId, 'пользователь не найден');
            return;
        }

        $cache = new CacheService();
        $cachekey = $chatId . ':message_to_user';
        $cache->set($cachekey, $user->telegram_username, 1200);
        
        $promptMessage = $this->localeService->get('support.message_to_user', [
            '{username}' => $user->telegram_username
        ]);
        $this->telegramApiService->sendMessageToChat($chatId, $promptMessage);
    }

    public function handleMessageToUserSendMessage(int $chatId, string $message): void
    {
        $cache = new CacheService();
        $username = $cache->get($chatId . ':message_to_user');

        if (!$username) {
            $this->telegramApiService->sendErrorMessage($chatId, 'Ошибка при отправке сообщения пользователю.');
            return;
        }

        $user = $this->userRepository->getByTelegramUsername($username);

        if (!$user) {
            $this->telegramApiService->sendErrorMessage($chatId, 'Ошибка при отправке сообщения пользователю.');
            return;
        }

        $this->telegramApiService->sendMessageToChat($user->telegram_id, $message);
        $this->telegramApiService->sendMessageToChat($chatId, 'Сообщение доставлено.');
    }

    public function handleMessageToAllStart(int $chatId): void
    {
        $cache = new CacheService();
        $cachekey = $chatId . ':message_to_all';
        $cache->set($cachekey, 'start', 1200);

        $this->telegramApiService->sendMessageToChat($chatId, 'Напишите сообщение для всех пользователей');
    }

    public function handleMessageToAllSend(string $message): void
    {
        $users = $this->userRepository->getAll();
        
        foreach ($users as $user) {
            $this->telegramApiService->sendMessageToChat($user->telegram_id, $message);
        }
    }
}
