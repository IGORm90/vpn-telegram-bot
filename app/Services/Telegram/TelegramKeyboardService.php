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
            ['text' => 'Баланс'],
            ['text' => 'Оплата доступа']
        ]
    ];

    const ADMIN_KEYBOARD = [
        [
            ['text' => 'Написать пользователю'],
            ['text' => 'Написать всем']
        ]
    ];

    private $isAdmin = false;

    public function __construct(?User $user = null)
    {
        if ($user) {
            $adminChatId = intval(env('ADMIN_CHAT_ID'));
            $this->isAdmin = $user->telegram_id === $adminChatId;
        }
    }

    public function getKeyboard()
    {
        if ($this->isAdmin) {
            $keyboard = array_merge(self::KEYBOARD, self::ADMIN_KEYBOARD);
        } else {
            $keyboard = self::KEYBOARD;
        }
        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
