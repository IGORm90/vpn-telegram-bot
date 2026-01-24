<?php

namespace App\Handlers;

use App\Exceptions\PaymentException;
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

        try {
            $this->processPayment($update);
        } catch (PaymentException $e) {
            Log::error($e->getMessage(), $e->getContext());

            if ($e->getChatId()) {
                $this->telegramApiService->sendErrorMessage($e->getChatId(), $e->getUserMessage());
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function processPayment(array $update): void
    {
        $message = $update['message'] ?? null;
        $successfulPayment = $message['successful_payment'] ?? null;

        if (!$successfulPayment) {
            throw PaymentException::missingSuccessfulPayment($update);
        }

        $chatId = $message['chat']['id'] ?? null;
        $payload = $successfulPayment['invoice_payload'] ?? null;
        $currency = $successfulPayment['currency'] ?? null;
        $totalAmount = $successfulPayment['total_amount'] ?? null;
        $telegramChargeId = $successfulPayment['telegram_payment_charge_id'] ?? null;
        $providerChargeId = $successfulPayment['provider_payment_charge_id'] ?? null;

        if (!$payload || !$telegramChargeId) {
            throw PaymentException::missingRequiredFields($payload, $telegramChargeId);
        }

        $invoice = StarInvoice::where('payload', $payload)->first();

        if (!$invoice) {
            throw PaymentException::invoiceNotFound($payload, $chatId);
        }

        $this->saveRawPayment($invoice, $successfulPayment);
        $this->validatePayment($invoice, $payload, $currency, $totalAmount, $chatId);
        $this->completePayment($invoice, $telegramChargeId, $providerChargeId);
        $this->sendConfirmation($chatId);
    }

    private function saveRawPayment(StarInvoice $invoice, array $successfulPayment): void
    {
        $rawSuccessfulPayment = json_encode($successfulPayment, JSON_UNESCAPED_UNICODE);
        $invoice->update([
            'raw_successful_payment' => $rawSuccessfulPayment,
        ]);
    }

    private function validatePayment(
        StarInvoice $invoice,
        string $payload,
        ?string $currency,
        ?int $totalAmount,
        ?int $chatId
    ): void {
        if ($payload !== $invoice->payload) {
            throw PaymentException::payloadMismatch($invoice->payload, $payload, $chatId);
        }

        if ($currency !== $invoice->currency) {
            throw PaymentException::currencyMismatch($invoice->currency, $currency, $payload, $chatId);
        }

        if ($totalAmount !== $invoice->amount) {
            throw PaymentException::amountMismatch($invoice->amount, $totalAmount, $payload, $chatId);
        }
    }

    private function completePayment(
        StarInvoice $invoice,
        string $telegramChargeId,
        ?string $providerChargeId
    ): void {
        $invoice->update([
            'status' => 'completed',
            'telegram_payment_charge_id' => $telegramChargeId,
            'provider_payment_charge_id' => $providerChargeId,
        ]);

        Log::info('Payment completed', [
            'invoice_id' => $invoice->id,
            'telegram_charge_id' => $telegramChargeId,
            'provider_charge_id' => $providerChargeId,
        ]);
    }

    private function sendConfirmation(?int $chatId): void
    {
        if ($chatId) {
            $this->telegramApiService->sendMessageToChat(
                $chatId,
                '✅ Оплата прошла успешно! Спасибо за покупку.'
            );
        }
    }
}
