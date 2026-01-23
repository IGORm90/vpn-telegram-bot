<?php

namespace App\Handlers;

use App\Models\StarInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Telegram\TelegramApiService;

class PreCheckoutHandler
{
    private TelegramApiService $telegramApiService;

    public function __construct()
    {
        $this->telegramApiService = new TelegramApiService();
    }

    public function handle(Request $request): void
    {
        $update = $request->all();

        Log::info('PreCheckoutHandler update', ['update' => $update]);

        $preCheckoutQuery = $update['pre_checkout_query'] ?? null;

        if (!$preCheckoutQuery) {
            Log::warning('Missing pre_checkout_query in update', ['update' => $update]);
            return;
        }

        $queryId = $preCheckoutQuery['id'] ?? null;
        $payload = $preCheckoutQuery['invoice_payload'] ?? null;
        $currency = $preCheckoutQuery['currency'] ?? null;
        $totalAmount = $preCheckoutQuery['total_amount'] ?? null;
        $chatId = $preCheckoutQuery['from']['id'] ?? null;

        if (!$queryId || !$payload) {
            Log::warning('Missing required fields in pre_checkout_query', [
                'queryId' => $queryId,
                'payload' => $payload,
            ]);
            return;
        }

        $rawPreCheckoutQuery = json_encode($preCheckoutQuery, JSON_UNESCAPED_UNICODE);

        // Находим запись по payload
        $invoice = StarInvoice::where('payload', $payload)->first();

        if (!$invoice) {
            Log::error('Invoice not found for payload', ['payload' => $payload]);
            $this->telegramApiService->answerPreCheckoutQuery($queryId, false, 'Счёт не найден');
            return;
        }

        // Сохраняем сырое тело запроса сразу после нахождения invoice
        $invoice->update([
            'raw_pre_checkout_query' => $rawPreCheckoutQuery,
        ]);

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
            Log::error('Currency mismatch', [
                'expected' => $invoice->currency,
                'received' => $currency,
            ]);
            $this->telegramApiService->answerPreCheckoutQuery($queryId, false, 'Неверная валюта');
            return;
        }

        // Проверяем сумму
        if ($totalAmount !== $invoice->amount) {
            Log::error('Amount mismatch', [
                'expected' => $invoice->amount,
                'received' => $totalAmount,
            ]);
            $this->telegramApiService->answerPreCheckoutQuery($queryId, false, 'Неверная сумма');
            return;
        }

        // Обновляем статус invoice
        $invoice->update([
            'status' => 'confirmed',
        ]);

        Log::info('Invoice confirmed', [
            'invoice_id' => $invoice->id,
            'payload' => $payload,
            'query_id' => $queryId,
        ]);

        // Отвечаем Telegram, что всё ок
        $this->telegramApiService->answerPreCheckoutQuery($queryId, true);
    }
}
