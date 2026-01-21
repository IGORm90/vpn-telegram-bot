<?php

namespace App\Services\Telegram;

use App\Models\User;


class TelegramKeyboardService
{
    /**
     * Маппинг кнопок: текст => [handler, needsUsername]
     */
    const BUTTON_HANDLERS = [
        'Главная' => ['handleMainPanel', false],
        'Подключить vpn' => ['handleConnectVpn', true],
        'Написать в поддержку' => ['handleSupport', false],
        'Подписка' => ['handleBalance', false],
        'Оплата доступа' => ['handleBalance', false],
        'Админ панель' => ['handleAdminPanel', false],
        'Написать пользователю' => ['handleMessageToUserStart', false],
        'Написать всем' => ['handleMessageToAllStart', false],
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

    const SUBSCRIPTION_KEYBOARD = [
        ['text' => '1 месяц - 1 ⭐️', 'callback_data' => 'subscribe_1_month'],
        ['text' => '3 месяца - 1 ⭐️', 'callback_data' => 'subscribe_3_months'],
        ['text' => '6 месяцев - 1 ⭐️', 'callback_data' => 'subscribe_6_months'],
        ['text' => '1 год - 1 ⭐️', 'callback_data' => 'subscribe_1_year']
    ];

    private $isAdmin = false;

    public function __construct(?User $user = null)
    {
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

        foreach (self::SUBSCRIPTION_KEYBOARD as $subscription) {
            $row = [
                [
                    'text' => $subscription['text'],
                    'callback_data' => $subscription['callback_data'],
                ]
            ];

            $inlineKeyboard[] = $row;
        }

        return [
            'inline_keyboard' => $inlineKeyboard,
        ];
    }
}
