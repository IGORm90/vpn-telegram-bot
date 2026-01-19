<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Handlers\MessageHandler;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;
use Illuminate\Validation\ValidationException;

class MainController extends Controller
{

    private MessageHandler $messageHandler;
    private TelegramApiService $telegramApiService;
    public function __construct()
    {
        $this->messageHandler = new MessageHandler();
        $this->telegramApiService = new TelegramApiService();
    }

    /**
     * Обработчик webhook запросов от Telegram
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request)
    {
        $chatId = null;
        try {
            $data = $request->all();
            $chatId = (int)($data['message']['chat']['id'] ?? null);

            $this->messageHandler->handle($request);
        } catch (ValidationException $e) {
            Log::error('Telegram webhook handler validation error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            if ($chatId) {
                $this->telegramApiService->sendErrorMessage($chatId);
            }
        } catch (\Exception $e) {
            Log::error('Telegram webhook handler error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            if ($chatId) {
                $this->telegramApiService->sendErrorMessage($chatId);
            }
        } finally {
            return response()->json(['ok' => true]);
        }
    }
}

