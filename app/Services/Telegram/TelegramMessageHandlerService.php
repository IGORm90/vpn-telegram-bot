<?php

namespace App\Services\Telegram;

use Carbon\Carbon;
use App\Entities\UserEntity;
use App\Services\UserService;
use App\Services\CacheService;
use App\Services\LocaleService;
use App\Services\VpnServerService;
use App\Repositories\UserRepository;

class TelegramMessageHandlerService
{
    private TelegramApiService $telegramApiService;
    private LocaleService $localeService;
    private UserRepository $userRepository;
    private VpnServerService $vpnServerService;
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->localeService = new LocaleService();
        $this->userRepository = new UserRepository();
        $this->vpnServerService = new VpnServerService();
    }

    public function handleStartMessage(): void
    {
        $user = UserEntity::getInstance();
        if (!$user->expiresAt !== null) {
            $isNewUser = true;
        } else {
            $isNewUser = false;
        }

        $keyboardService = new TelegramKeyboardService($user->getModel());
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        
        if ($isNewUser) {
            $imagePath = __DIR__ . '/../../../resources/images/image.png';
            
            $message = $this->localeService->get('welcome.message');
            $this->telegramApiService->sendMessageToChatWithPhoto($user->telegramId, $imagePath, $message, $options);

            $this->telegramApiService->sendMessageToChat($this->localeService->get('welcome.instruction'));
            $this->telegramApiService->sendMessageToChat($this->localeService->get('info.available_servers'));
        } else {
            $this->telegramApiService->sendMessageToChat($this->localeService->get('support.start'), $options);
            $this->telegramApiService->sendMessageToChat($this->localeService->get('info.available_servers'));
        }
    }

    public function handleMainPanel(): void
    {
        $user = UserEntity::getInstance();
        $keyboardService = new TelegramKeyboardService($user->getModel());
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat('Главная', $options);
    }

    public function handleAdminPanel(): void
    {
        $user = UserEntity::getInstance();
        $keyboardService = new TelegramKeyboardService($user->getModel());
        $options = [
            'reply_markup' => $keyboardService->getAdminKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat('Админ панель', $options);
    }

    public function handleConnectVpn(int $serverId): void
    {
        $userService = new UserService();
        $user = UserEntity::getInstance();

        $server = $this->vpnServerService->getServerById($serverId);

        if (!$server) {
            $errorMessage = $this->localeService->get('errors.server_not_found');
            $this->telegramApiService->sendMessageToChat($errorMessage);
            return;
        }

        $configString = $userService->getUserConfig($user->telegramId, $server, $user->telegramUsername);
        if (!$configString) {
            $errorMessage = $this->localeService->get('errors.config_creation_failed');
            $this->telegramApiService->sendMessageToChat($errorMessage);
            return;
        }


        $userService->updateUser($user->id, [
            'expires_at' => Carbon::now()->addDays(14),
        ]);
        $this->telegramApiService->sendMessageToChat($configString);
    }

    public function handleSupport(): void
    {
        $user = UserEntity::getInstance();
        $cache = new CacheService();
        $cachekey = $user->telegramId . ':support';
        $cache->set($cachekey, $user->telegramId, 1200);
        
        $promptMessage = $this->localeService->get('support.prompt');
        $this->telegramApiService->sendMessageToChat($promptMessage);
    }

    public function handleSubscription(): void
    {
        $user = UserEntity::getInstance();

        $this->telegramApiService->sendMessageToChat($this->localeService->get('subscription.expires_at', [
            '{expires_at}' => $user->expiresAt->format('d.m.Y')
        ]));
    }

    public function handleServersList(): void
    {
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getVpnServersKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat('Список VPN серверов', $options);
    }


    public function handleListSubscriptions(): void
    {
        $user = UserEntity::getInstance();

        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getSubscriptionsKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($this->localeService->get('subscription.subscription_message', [
            '{expires_at}' => $user->expiresAt->format('d.m.Y')
        ]), $options);
    }

    public function handleMessageToUserStart(): void
    {
        $user = UserEntity::getInstance();

        $cache = new CacheService();
        $cachekey = $user->telegramId . ':message_to_user';
        $cache->set($cachekey, 'start', 1200);

        $this->telegramApiService->sendMessageToChat('Напишите username пользователя');
    }

    public function handleMessageToUserSaveUsername(string $usernameForMessage): void
    {
        $user = UserEntity::getInstance();
        $userForMessage = $this->userRepository->getByTelegramUsername($usernameForMessage);
        if (!$user) {
            $this->telegramApiService->sendErrorMessage('пользователь не найден');
            return;
        }

        $cache = new CacheService();
        $cachekey = $user->telegramId . ':message_to_user';
        $cache->set($cachekey, $userForMessage->telegram_username, 1200);
        
        $promptMessage = $this->localeService->get('support.message_to_user', [
            '{username}' => $userForMessage->telegram_username
        ]);
        $this->telegramApiService->sendMessageToChat($promptMessage);
    }

    public function handleMessageToUserSendMessage(string $message): void
    {
        $user = UserEntity::getInstance();
        $cache = new CacheService();
        $username = $cache->get($user->telegramId . ':message_to_user');

        if (!$username) {
            $this->telegramApiService->sendErrorMessage('Ошибка при отправке сообщения пользователю.');
            return;
        }

        $user = $this->userRepository->getByTelegramUsername($username);

        if (!$user) {
            $this->telegramApiService->sendErrorMessage('Ошибка при отправке сообщения пользователю.');
            return;
        }

        $this->telegramApiService->sendMessageToChat($message, [], $user->telegram_id);
        $this->telegramApiService->sendMessageToChat('Сообщение доставлено.');
    }

    public function handleMessageToAllStart(): void
    {
        $user = UserEntity::getInstance();
        $cache = new CacheService();
        $cachekey = $user->telegramId . ':message_to_all';
        $cache->set($cachekey, 'start', 1200);

        $this->telegramApiService->sendMessageToChat('Напишите сообщение для всех пользователей');
    }

    public function handleMessageToAllSend(string $message): void
    {
        $users = $this->userRepository->getAll();
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        foreach ($users as $user) {
            $this->telegramApiService->sendMessageToChat($message, $options, $user->telegram_id);
        }
        $this->telegramApiService->sendMessageToChat('Сообщение доставлено всем пользователям.');
    }
}
