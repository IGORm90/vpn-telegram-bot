<?php

namespace App\Handlers;

use App\Exceptions\PaymentException;
use App\Models\StarInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        try {
            $this->processPreCheckout($preCheckoutQuery, $queryId);
            $this->telegramApiService->answerPreCheckoutQuery($queryId, true);
        } catch (PaymentException $e) {
            Log::error($e->getMessage(), $e->getContext());
            $this->telegramApiService->answerPreCheckoutQuery($queryId, false, $e->getUserMessage());
        }
    }

    private function processPreCheckout(array $preCheckoutQuery, ?string $queryId): void
    {
        $payload = $preCheckoutQuery['invoice_payload'] ?? null;
        $currency = $preCheckoutQuery['currency'] ?? null;
        $totalAmount = $preCheckoutQuery['total_amount'] ?? null;

        if (!$queryId || !$payload) {
            throw PaymentException::missingPreCheckoutFields($queryId, $payload);
        }

        $invoice = StarInvoice::where('payload', $payload)->first();

        if (!$invoice) {
            throw PaymentException::invoiceNotFound($payload, null);
        }

        $this->saveRawPreCheckout($invoice, $preCheckoutQuery);
        $this->validatePreCheckout($invoice, $currency, $totalAmount);
        $this->confirmInvoiceAndExtendSubscription($invoice);
    }

    private function saveRawPreCheckout(StarInvoice $invoice, array $preCheckoutQuery): void
    {
        $rawPreCheckoutQuery = json_encode($preCheckoutQuery, JSON_UNESCAPED_UNICODE);
        $invoice->update([
            'raw_pre_checkout_query' => $rawPreCheckoutQuery,
        ]);
    }

    private function validatePreCheckout(
        StarInvoice $invoice,
        ?string $currency,
        ?int $totalAmount
    ): void {
        if ($currency !== $invoice->currency) {
            throw PaymentException::currencyMismatch(
                $invoice->currency,
                $currency ?? 'null',
                $invoice->payload,
                null
            );
        }

        if ($totalAmount !== $invoice->amount) {
            throw PaymentException::amountMismatch(
                $invoice->amount,
                $totalAmount ?? 0,
                $invoice->payload,
                null
            );
        }
    }

    private function confirmInvoiceAndExtendSubscription(StarInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->update([
                'status' => 'confirmed',
            ]);

            $user = $invoice->user;
            $oldBalance = $user->balance ?? 0;
            $balanceAmount = $invoice->metadata['balance_amount'] ?? 0;

            $user->update([
                'balance' => $oldBalance + $balanceAmount,
            ]);

            Log::info('Invoice confirmed and balance increased', [
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'balance_amount' => $balanceAmount,
                'old_balance' => $oldBalance,
                'new_balance' => $oldBalance + $balanceAmount,
            ]);
        });
    }
}
