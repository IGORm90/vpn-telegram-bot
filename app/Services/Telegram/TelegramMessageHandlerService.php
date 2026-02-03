<?php

namespace App\Services\Telegram;

use Carbon\Carbon;
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

    public function handleStartMessage(int $chatId, string $username): void
    {
        $user = $this->userRepository->findByTelegramId($chatId);

        $keyboardService = new TelegramKeyboardService($user);
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        
        if (!$user) {
            $this->userRepository->create([
                'telegram_id' => $chatId,
                'telegram_username' => $username,
                'is_active' => true,
                'expires_at' => Carbon::now()->addDays(14),
            ]);

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
        $user = $this->userRepository->findByTelegramId($chatId);
        $keyboardService = new TelegramKeyboardService($user);
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, 'Главная', $options);
    }

    public function handleAdminPanel(int $chatId): void
    {
        $user = $this->userRepository->findByTelegramId($chatId);
        $keyboardService = new TelegramKeyboardService($user);
        $options = [
            'reply_markup' => $keyboardService->getAdminKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, 'Админ панель', $options);
    }

    public function handleConnectVpn(int $chatId, string $serverId, string $username): void
    {
        $userService = new UserService();

        $server = $this->vpnServerService->getServerById($serverId);

        if (!$server) {
            $errorMessage = $this->localeService->get('errors.server_not_found');
            $this->telegramApiService->sendMessageToChat($chatId, $errorMessage);
            return;
        }

        $configString = $userService->getUserConfig($chatId, $server, $username);
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

    public function handleSubscription(int $chatId): void
    {
        $user = $this->userRepository->findByTelegramId($chatId);
        if (!$user) {
            $this->telegramApiService->sendErrorMessage($chatId);
            return;
        }

        $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('subscription.expires_at', [
            '{expires_at}' => $user->expires_at->format('d.m.Y')
        ]));
    }

    public function handleServersList(int $chatId): void
    {
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getVpnServersKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat($chatId, 'Список VPN серверов', $options);
    }


    public function handleListSubscriptions(int $chatId): void
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
        $this->telegramApiService->sendMessageToChat($chatId, $this->localeService->get('subscription.subscription_message', [
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

    public function handleMessageToAllSend(int $chatId, string $message): void
    {
        $users = $this->userRepository->getAll();
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        foreach ($users as $user) {
            $this->telegramApiService->sendMessageToChat($user->telegram_id, $message, $options);
        }
        $this->telegramApiService->sendMessageToChat($chatId, 'Сообщение доставлено всем пользователям.');
    }
}
