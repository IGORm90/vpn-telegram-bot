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
        $user = $this->userRepository->findByTelegramId($chatId);

        $keyboardService = new TelegramKeyboardService($user);
        $options = [
            'reply_markup' => $keyboardService->getKeyboard(),
        ];
        
        if (!$user) {
            $imagePath = __DIR__ . '/../../../resources/images/image.png';
            
            $message = $this->textService->get('welcome.message');
            $this->telegramApiService->sendMessageToChatWithPhoto($chatId, $imagePath, $message, $options);

            $this->telegramApiService->sendMessageToChat($chatId, $this->textService->get('welcome.instruction'));
            $this->telegramApiService->sendMessageToChat($chatId, $this->textService->get('info.available_servers'));
        } else {
            $this->telegramApiService->sendMessageToChat($chatId, $this->textService->get('support.start'), $options);
            $this->telegramApiService->sendMessageToChat($chatId, $this->textService->get('info.available_servers'));
        }
    }
}
