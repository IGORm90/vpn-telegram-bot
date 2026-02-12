<?php

namespace App\Services\Telegram;

use Carbon\Carbon;
use App\Entities\UserEntity;
use App\Models\User;
use App\Jobs\SendBroadcastMessage;
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
        if ($user->expiresAt === null) {
            $isNewUser = true;
            $this->userRepository->updateExpiration($user->getModel(), Carbon::now()->addDays(14));
        } else {
            $isNewUser = false;
        }
        
        if ($isNewUser) {
            $imagePath = __DIR__ . '/../../../resources/images/image.png';
            
            $message = $this->localeService->get('welcome.message');
            $this->telegramApiService->sendMessageToChatWithPhoto($user->telegramId, $imagePath, $message);

            $this->telegramApiService->sendMessageToChat($this->localeService->get('welcome.instruction'));
            $this->telegramApiService->sendMessageToChat($this->localeService->get('info.available_servers'));
        } else {
            $this->telegramApiService->sendMessageToChat($this->localeService->get('support.start'));
            $this->telegramApiService->sendMessageToChat($this->localeService->get('info.available_servers'));
        }
    }

    public function handleMainPanel(): void
    {
        $this->telegramApiService->sendMessageToChat('Главная');
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

        $expiresAtFormatted = $user->expiresAt
            ? $user->expiresAt->format('d.m.Y')
            : 'не активна';

        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getActivationKeyboard($user->balance),
        ];

        $this->telegramApiService->sendMessageToChat($this->localeService->get('subscription.subscription_message', [
            '{expires_at}' => $expiresAtFormatted,
            '{balance}' => $user->balance,
        ]), $options);
    }

    public function handleServersList(): void
    {
        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getVpnServersKeyboard(),
        ];
        $this->telegramApiService->sendMessageToChat('Список VPN серверов', $options);
    }


    public function handleBalance(): void
    {
        $user = UserEntity::getInstance();

        $keyboardService = new TelegramKeyboardService();
        $options = [
            'reply_markup' => $keyboardService->getSubscriptionsKeyboard(),
        ];
        $balance = $user->balance;
        $this->telegramApiService->sendMessageToChat($this->localeService->get('subscription.balance_message', [
            '{balance}' => $balance
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
        $count = User::count();

        User::select('telegram_id')->chunk(200, function ($users) use ($message) {
            foreach ($users as $user) {
                dispatch(new SendBroadcastMessage($message, $user->telegram_id));
            }
        });

        $this->telegramApiService->sendMessageToChat(
            "Рассылка поставлена в очередь для {$count} пользователей."
        );
    }

    public function handleInviteFriend(): void
    {
        $user = UserEntity::getInstance();
        
        // Убедимся, что у пользователя есть реферальный хэш
        $referralHash = $this->userRepository->ensureReferralHash($user->getModel());
        
        $botUsername = env('TELEGRAM_BOT_USERNAME');
        $referralLink = "https://t.me/{$botUsername}?start={$referralHash}";
        
        $message = $this->localeService->get('referral.invite_message', [
            '{link}' => $referralLink
        ]);
        
        $this->telegramApiService->sendMessageToChat($message);
    }

    public function handleStartMessageWithReferral(?string $referralHash): void
    {
        $user = UserEntity::getInstance();
        $isNewUser = $user->expiresAt === null;
        
        if ($isNewUser) {
            // Базовый срок подписки - 2 недели
            $expirationDays = 14;
            $referralBonusApplied = false;
            
            // Обработка реферальной ссылки
            if ($referralHash && $referralHash !== $user->referralHash) {
                $referrer = $this->userRepository->findByReferralHash($referralHash);
                
                if ($referrer && $referrer->id !== $user->id) {
                    // Сохраняем информацию о реферере
                    $this->userRepository->setReferredByHash($user->getModel(), $referralHash);
                    
                    // Добавляем бонусную неделю новому пользователю
                    $expirationDays += 7;
                    $referralBonusApplied = true;
                    
                    // Добавляем неделю подписки рефереру
                    $this->userRepository->addWeekToSubscription($referrer);
                    
                    // Отправляем уведомление рефереру
                    $referrerMessage = $this->localeService->get('referral.referrer_bonus');
                    $this->telegramApiService->sendMessageToChat($referrerMessage, [], $referrer->telegram_id);
                }
            }
            
            // Устанавливаем срок подписки
            $this->userRepository->updateExpiration($user->getModel(), Carbon::now()->addDays($expirationDays));
            
            // Отправляем приветственное сообщение
            $imagePath = __DIR__ . '/../../../resources/images/image.png';
            $message = $this->localeService->get('welcome.message');
            $this->telegramApiService->sendMessageToChatWithPhoto($user->telegramId, $imagePath, $message);
            
            // Если пришел по реферальной ссылке - сообщаем о бонусе
            if ($referralBonusApplied) {
                $bonusMessage = $this->localeService->get('referral.new_user_bonus');
                $this->telegramApiService->sendMessageToChat($bonusMessage);
            }
            
            $this->telegramApiService->sendMessageToChat($this->localeService->get('welcome.instruction'));
            $this->telegramApiService->sendMessageToChat($this->localeService->get('info.available_servers'));
        } else {
            $this->telegramApiService->sendMessageToChat($this->localeService->get('support.start'));
            $this->telegramApiService->sendMessageToChat($this->localeService->get('info.available_servers'));
        }
    }
}
