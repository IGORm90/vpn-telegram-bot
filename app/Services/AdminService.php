<?php

namespace App\Services;

use App\Services\Telegram\TelegramApiService;

class AdminService
{

    private int $adminChatId;
    private TelegramApiService $telegramApiService;
    public function __construct()
    {
        $this->adminChatId = intval(env('ADMIN_CHAT_ID'));
        $this->telegramApiService = new TelegramApiService();
    }

    public function sendMesssageToAdmin(string $message): void
    {
        $this->telegramApiService->sendMessageToChat($message, [], $this->adminChatId);
    }
}

