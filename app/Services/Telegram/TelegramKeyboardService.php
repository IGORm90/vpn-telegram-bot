<?php

namespace App\Services\Telegram;


class TelegramKeyboardService
{

    const KEYBOARD = [
       ['text' => 'Подключить vpn'],
       ['text' =>  'Написать в поддержку'],
    //    'Оплата доступа',
    //    'Баланс',
    ];

    public function getKeyboard()
    {
        $buttons = [];
        $chunks = array_chunk(self::KEYBOARD, 2);

        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $button) {
                $row[] = ['text' => $button['text']];
            }
            $buttons[] = $row;
        }

        return [
            'keyboard' => $buttons,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
