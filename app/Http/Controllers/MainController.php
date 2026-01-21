<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Handlers\MessageHandler;
use App\Handlers\CallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Handlers\PreCheckoutHandler;
use App\Handlers\SuccessfulPaymentHandler;
use App\Services\Telegram\TelegramApiService;
use Illuminate\Validation\ValidationException;

class MainController extends Controller
{
    private MessageHandler $messageHandler;
    private CallbackHandler $callbackHandler;
    private PreCheckoutHandler $preCheckoutHandler;
    private SuccessfulPaymentHandler $successfulPaymentHandler;
    private TelegramApiService $telegramApiService;

    public function __construct()
    {
        $this->messageHandler = new MessageHandler();
        $this->callbackHandler = new CallbackHandler();
        $this->preCheckoutHandler = new PreCheckoutHandler();
        $this->successfulPaymentHandler = new SuccessfulPaymentHandler();
        $this->telegramApiService = new TelegramApiService();
    }

    /**
     * Обработчик webhook запросов от Telegram
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(Request $request): JsonResponse
    {
        $chatId = null;
        try {
            $data = $request->all();
            $chatId = $this->extractChatId($data);

            $this->routeUpdate($request, $data);
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
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Маршрутизация update по типу
     */
    private function routeUpdate(Request $request, array $data): void
    {
        // 1. Callback query (нажатие inline-кнопки)
        if (isset($data['callback_query'])) {
            $this->callbackHandler->handle($request);
            return;
        }

        // 2. Pre-checkout query (предварительная проверка платежа)
        if (isset($data['pre_checkout_query'])) {
            $this->preCheckoutHandler->handle($request);
            return;
        }

        // 3. Successful payment (успешный платёж внутри message)
        if (isset($data['message']['successful_payment'])) {
            $this->successfulPaymentHandler->handle($request);
            return;
        }

        // 4. Обычное текстовое сообщение
        if (isset($data['message'])) {
            $this->messageHandler->handle($request);
            return;
        }

        Log::warning('Unknown update type', ['data' => $data]);
    }

    /**
     * Извлечение chat_id из различных типов update
     */
    private function extractChatId(array $data): ?int
    {
        // Из обычного сообщения или successful_payment
        if (isset($data['message']['chat']['id'])) {
            return (int) $data['message']['chat']['id'];
        }

        // Из callback_query
        if (isset($data['callback_query']['message']['chat']['id'])) {
            return (int) $data['callback_query']['message']['chat']['id'];
        }

        // Из pre_checkout_query (chat_id нет, но есть user_id)
        if (isset($data['pre_checkout_query']['from']['id'])) {
            return (int) $data['pre_checkout_query']['from']['id'];
        }

        return null;
    }
}

