<?php

namespace App\Services\Telegram;

use App\Services\TextService;
use App\Repositories\UserRepository;

class TelegramMessageHandlerService
{
    private TelegramApiService $telegramApiService;
    private TextService $textService;
    private UserRepository $userRepository;
    
    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
        $this->textService = new TextService();
        $this->userRepository = new UserRepository();
    }

    public function handleStartMessage(int $chatId): void
    {
        $username = $this->userRepository->findByTelegramId($chatId);

        $options = [
            'reply_markup' => (new TelegramKeyboardService())->getKeyboard(),
        ];
        if (!$username) {
            $imagePath = __DIR__ . '/../../../resources/images/image.png';
            
            $message = $this->textService->get('welcome.message');
            $this->telegramApiService->sendMessageToChatWithPhoto($chatId, $imagePath, $message, $options);

            $this->telegramApiService->sendMessageToChat($chatId, $this->textService->get('welcome.instruction'));
        } else {
            $this->telegramApiService->sendMessageToChat($chatId, $this->textService->get('support.start'), $options);
        }
    }
}
