<?php

namespace App\Handlers;

use App\Models\StarInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;

class SuccessfulPaymentHandler
{
    private TelegramApiService $telegramApiService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
    }

    public function handle(Request $request): void
    {
        $update = $request->all();

        Log::info('SuccessfulPaymentHandler update', ['update' => $update]);

        $message = $update['message'] ?? null;
        $successfulPayment = $message['successful_payment'] ?? null;

        if (!$successfulPayment) {
            Log::warning('Missing successful_payment in message', ['update' => $update]);
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $payload = $successfulPayment['invoice_payload'] ?? null;
        $currency = $successfulPayment['currency'] ?? null;
        $totalAmount = $successfulPayment['total_amount'] ?? null;
        $telegramChargeId = $successfulPayment['telegram_payment_charge_id'] ?? null;
        $providerChargeId = $successfulPayment['provider_payment_charge_id'] ?? null;

        if (!$payload || !$telegramChargeId) {
            Log::warning('Missing required fields in successful_payment', [
                'payload' => $payload,
                'telegramChargeId' => $telegramChargeId,
            ]);
            return;
        }

        $this->processSuccessfulPayment(
            $chatId,
            $payload,
            $currency,
            $totalAmount,
            $telegramChargeId,
            $providerChargeId
        );
    }

    private function processSuccessfulPayment(
        ?int $chatId,
        string $payload,
        ?string $currency,
        ?int $totalAmount,
        string $telegramChargeId,
        ?string $providerChargeId
    ): void {
        // Находим запись по payload
        $invoice = StarInvoice::where('payload', $payload)->first();

        if (!$invoice) {
            Log::error('Invoice not found for payload', ['payload' => $payload]);
            if ($chatId) {
                $this->telegramApiService->sendErrorMessage($chatId, 'Счёт не найден. Обратитесь в поддержку.');
            }
            return;
        }


        if ($payload !== $invoice->payload) {
            Log::error('payload mismatch in successful_payment', [
                'expected' => $invoice->payload,
                'received' => $payload,
                'payload' => $payload,
            ]);
            if ($chatId) {
                $this->telegramApiService->sendErrorMessage($chatId, 'Ошибка payload. Обратитесь в поддержку.');
            }
            return;
        }

        // Проверяем валюту
        if ($currency !== $invoice->currency) {
            Log::error('Currency mismatch in successful_payment', [
                'expected' => $invoice->currency,
                'received' => $currency,
                'payload' => $payload,
            ]);
            if ($chatId) {
                $this->telegramApiService->sendErrorMessage($chatId, 'Ошибка валюты. Обратитесь в поддержку.');
            }
            return;
        }

        // Проверяем сумму
        if ($totalAmount !== $invoice->amount) {
            Log::error('Amount mismatch in successful_payment', [
                'expected' => $invoice->amount,
                'received' => $totalAmount,
                'payload' => $payload,
            ]);
            if ($chatId) {
                $this->telegramApiService->sendErrorMessage($chatId, 'Ошибка суммы. Обратитесь в поддержку.');
            }
            return;
        }

        // Обновляем запись
        $invoice->update([
            'status' => 'complited',
            'telegram_payment_charge_id' => $telegramChargeId,
            'provider_payment_charge_id' => $providerChargeId,
        ]);

        Log::info('Payment completed successfully', [
            'invoice_id' => $invoice->id,
            'payload' => $payload,
            'telegram_charge_id' => $telegramChargeId,
            'provider_charge_id' => $providerChargeId,
        ]);

        // Отправляем подтверждение пользователю
        if ($chatId) {
            $this->telegramApiService->sendMessageToChat(
                $chatId,
                '✅ Оплата прошла успешно! Спасибо за покупку.'
            );
        }
    }
}
