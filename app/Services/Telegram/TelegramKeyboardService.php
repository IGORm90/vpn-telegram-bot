<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Services\VpnServerService;


class TelegramKeyboardService
{
    /**
     * Маппинг кнопок и команд: текст => [handler]
     * App\Services\Telegram\TelegramMessageHandlerService
     */
    const BUTTON_HANDLERS = [
        '/start' => ['handleStartMessage'],
        '/admin' => ['handleMainPanel'],
        'Главная' => ['handleMainPanel'],
        'Подключить vpn' => ['handleServersList'],
        'Написать в поддержку' => ['handleSupport'],
        'Подписка' => ['handleSubscription'],
        'Оплата доступа' => ['handleListSubscriptions'],
        'Админ панель' => ['handleAdminPanel'],
        'Написать пользователю' => ['handleMessageToUserStart'],
        'Написать всем' => ['handleMessageToAllStart'],
    ];

    const KEYBOARD = [
        [
            ['text' => 'Подключить vpn'],
            ['text' => 'Написать в поддержку']
        ],
        [
            ['text' => 'Подписка'],
            ['text' => 'Оплата доступа']
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

    public function __construct(?User $user = null)
    {
        $this->subscriptionService = new SubscriptionService();
        $this->vpnServerService = new VpnServerService();

        if ($user) {
            $adminChatId = intval(env('ADMIN_CHAT_ID'));
            $this->isAdmin = intval($user->telegram_id) === $adminChatId;
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
}
