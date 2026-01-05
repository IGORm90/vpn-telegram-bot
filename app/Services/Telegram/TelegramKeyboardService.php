<?php

namespace App\Services\Telegram;


class TelegramKeyboardService
{

    const KEYBOARD = [
       'connect_vpn' => 'Подключить vpn',
       'support' => 'Написать в поддержку',
       'pay' => 'Оплата доступа',
       'balance' => 'Баланс',
    ];

    public function getKeyboard()
    {
        $keyboardValues = [];
        foreach (self::KEYBOARD as $key => $value) {
            $keyboardValues[] = [
                ['text' => $value, 'callback_data' => $key]
            ];
        }
        return [
            'inline_keyboard' => $keyboardValues,
        ];
    }

    public function getKeyboardArray(){
       return self::KEYBOARD;
    }
}