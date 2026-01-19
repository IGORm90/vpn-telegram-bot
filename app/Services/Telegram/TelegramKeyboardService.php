<?php

namespace App\Services\Telegram;

use App\Models\User;


class TelegramKeyboardService
{

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
}
