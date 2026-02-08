<?php

namespace App\Services\Telegram;

use App\Entities\UserEntity;
use App\Services\VpnServerService;


class TelegramKeyboardService
{
    /**
     * Маппинг кнопок и команд: текст => [handler]
     * App\Services\Telegram\TelegramMessageHandlerService
     */
    const BUTTON_HANDLERS = [
        '/start' => ['handler' => 'handleStartMessage'],
        '/admin' => ['handler' => 'handleMainPanel'],
        'Главная' => ['handler' => 'handleMainPanel'],
        'Подключить vpn' => ['handler' => 'handleServersList'],
        'Написать в поддержку' => ['handler' => 'handleSupport'],
        'Подписка' => ['handler' => 'handleSubscription'],
        'Баланс' => ['handler' => 'handleBalance'],
        'Админ панель' => ['handler' => 'handleAdminPanel'],
        'Написать пользователю' => ['handler' => 'handleMessageToUserStart'],
        'Написать всем' => ['handler' => 'handleMessageToAllStart'],
    ];

    const KEYBOARD = [
        [
            ['text' => 'Подключить vpn'],
            ['text' => 'Написать в поддержку']
        ],
        [
            ['text' => 'Подписка'],
            ['text' => 'Баланс']
        ]
    ];

    const ADMIN_KEYBOARD = [
        [
            ['text' => 'Написать пользователю'],
            ['text' => 'Написать всем']
        ],
        [
            ['text' => 'Главная']
        ]
    ];

    const ADMIN_BUTTON = [
        [
            ['text' => 'Админ панель']
        ]
    ];

    private $isAdmin = false;
    private SubscriptionService $subscriptionService;
    private VpnServerService $vpnServerService;

    public function __construct()
    {
        $this->subscriptionService = new SubscriptionService();
        $this->vpnServerService = new VpnServerService();

        $user = UserEntity::getInstance();
        if ($user) {
            $adminChatId = intval(env('ADMIN_CHAT_ID'));
            $this->isAdmin = intval($user->telegramId) === $adminChatId;
        }
    }

    public function getKeyboard()
    {
        if ($this->isAdmin) {
            $keyboard = array_merge(self::KEYBOARD, self::ADMIN_BUTTON);
        } else {
            $keyboard = self::KEYBOARD;
        }
        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    public function getAdminKeyboard()
    {
        return [
            'keyboard' => self::ADMIN_KEYBOARD,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Получить inline-клавиатуру с подписками
     *
     * @return array
     */
    public function getSubscriptionsKeyboard(): array
    {
        $inlineKeyboard = [];
        $subscriptionConfig = $this->subscriptionService->getSubscriptionConfig();

        foreach ($subscriptionConfig as $callbackData => $config) {
            $text = $config['title'] . ' - ' . $config['amount'] . ' ⭐️';
            $row = [
                [
                    'text' => $text,
                    'callback_data' => $callbackData,
                ]
            ];

            $inlineKeyboard[] = $row;
        }

        return [
            'inline_keyboard' => $inlineKeyboard,
        ];
    }

    /**
     * Получить inline-клавиатуру со списком VPN серверов
     *
     * @return array
     */
    public function getVpnServersKeyboard(): array
    {
        $inlineKeyboard = [];
        $servers = $this->vpnServerService->getAllServers();

        foreach ($servers as $server) {
            $text = $server->title . ' ' . $server->flag_emoji;
            $row = [
                [
                    'text' => $text,
                    'callback_data' => 'server_' . $server->id,
                ]
            ];

            $inlineKeyboard[] = $row;
        }

        return [
            'inline_keyboard' => $inlineKeyboard,
        ];
    }

    /**
     * Получить inline-клавиатуру для активации подписки
     *
     * @param int $userBalance Текущий баланс пользователя
     * @return array
     */
    public function getActivationKeyboard(int $userBalance): array
    {
        $inlineKeyboard = [];
        $activationConfig = $this->subscriptionService->getActivationConfig();

        foreach ($activationConfig as $callbackData => $config) {
            $isAvailable = $userBalance >= $config['balance_cost'];
            $text = $config['title'];
            
            if ($isAvailable) {
                $text = '✅ ' . $text;
            } else {
                $text = '🔒 ' . $text;
            }

            $row = [
                [
                    'text' => $text,
                    'callback_data' => $callbackData,
                ]
            ];

            $inlineKeyboard[] = $row;
        }

        return [
            'inline_keyboard' => $inlineKeyboard,
        ];
    }
}
