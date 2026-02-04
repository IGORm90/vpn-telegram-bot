<?php

namespace App\Services;

use App\Repositories\StarInvoiceRepository;
use App\Services\Telegram\TelegramApiService;
use Illuminate\Support\Facades\Log;

class StarInvoiceService
{
    const MONTHLY_STAR_PRICE = 1;
    
    private StarInvoiceRepository $starInvoiceRepository;
    private TelegramApiService $telegramApiService;
    
    public function __construct()
    {
        $this->starInvoiceRepository = new StarInvoiceRepository();
        $this->telegramApiService = new TelegramApiService();
    }

    /**
     * Создать и отправить инвойс пользователю
     *
     * @param int $userId Telegram ID пользователя
     * @param int $amount Количество звезд
     * @param string $title Заголовок инвойса
     * @param string $description Описание инвойса
     * @param array $options Дополнительные опции (start_parameter, photo_url и т.д.)
     * @return array|null
     */
    public function createAndSendInvoice(
        int $userId,
        int $amount = self::MONTHLY_STAR_PRICE,
        string $title = "Оплата VPN 1 мес.",
        string $description = "Доступ к VPN на 1 месяц",
        array $options = []
    ): ?array
    {
        $invoice = $this->starInvoiceRepository->create($userId, $amount);

        Log::info('Star invoice created', [
            'invoice_id' => $invoice->id,
            'user_id' => $userId,
            'amount' => $amount,
            'payload' => $invoice->payload,
        ]);

        // Отправляем инвойс через Telegram API
        $response = $this->telegramApiService->sendInvoice(
            $userId,
            $title,
            $description,
            $invoice->payload,
            $amount,
            $options
        );

        if (!$response || !$response['success']) {
            // Если отправка не удалась, обновляем статус
            $this->starInvoiceRepository->update($invoice, [
                'status' => 'failed',
            ]);
            
            Log::error('Failed to send star invoice', [
                'invoice_id' => $invoice->id,
                'error' => $response['message'] ?? 'Unknown error',
            ]);
        }

        return $response;
    }

    /**
     * Обработать PreCheckoutQuery
     * Вызывается когда пользователь подтвердил оплату, но деньги еще не списаны
     *
     * @param array $preCheckoutQuery Данные из update
     * @return array|null
     */
    public function handlePreCheckoutQuery(array $preCheckoutQuery): ?array
    {
        $queryId = $preCheckoutQuery['id'];
        $payload = $preCheckoutQuery['invoice_payload'];
        $amount = $preCheckoutQuery['total_amount'];
        $currency = $preCheckoutQuery['currency'];
        $userId = $preCheckoutQuery['from']['id'];

        Log::info('PreCheckoutQuery received', [
            'query_id' => $queryId,
            'payload' => $payload,
            'amount' => $amount,
            'user_id' => $userId,
        ]);

        // Проверяем, есть ли такая транзакция в БД
        $invoice = $this->starInvoiceRepository->findByPayload($payload);

        if (!$invoice) {
            Log::error('Invoice not found for PreCheckoutQuery', [
                'payload' => $payload,
                'query_id' => $queryId,
            ]);

            return $this->telegramApiService->answerPreCheckoutQuery(
                $queryId,
                false,
                'Транзакция не найдена'
            );
        }

        // Проверяем соответствие суммы
        if ($invoice->amount != $amount) {
            Log::error('Amount mismatch in PreCheckoutQuery', [
                'expected' => $invoice->amount,
                'received' => $amount,
                'query_id' => $queryId,
            ]);


            return $this->telegramApiService->answerPreCheckoutQuery(
                $queryId,
                false,
                'Неверная сумма платежа'
            );
        }

        // Проверяем валюту
        if ($currency !== 'XTR') {
            Log::error('Currency mismatch in PreCheckoutQuery', [
                'expected' => 'XTR',
                'received' => $currency,
                'query_id' => $queryId,
            ]);


            return $this->telegramApiService->answerPreCheckoutQuery(
                $queryId,
                false,
                'Неверная валюта'
            );
        }

        // Проверяем, что транзакция еще не была обработана
        if ($invoice->status !== 'pending') {
            Log::error('Invoice already processed', [
                'invoice_id' => $invoice->id,
                'status' => $invoice->status,
                'query_id' => $queryId,
            ]);


            return $this->telegramApiService->answerPreCheckoutQuery(
                $queryId,
                false,
                'Транзакция уже обработана'
            );
        }

        // Все проверки пройдены, разрешаем списание
        Log::info('PreCheckoutQuery approved', [
            'invoice_id' => $invoice->id,
            'query_id' => $queryId,
        ]);

        return $this->telegramApiService->answerPreCheckoutQuery($queryId, true);
    }

    /**
     * Обработать успешный платеж
     * Вызывается после того как деньги списаны
     *
     * @param array $successfulPayment Данные из message->successful_payment
     * @param int $userId Telegram ID пользователя
     * @return bool
     */
    public function handleSuccessfulPayment(array $successfulPayment, int $userId): bool
    {
        $payload = $successfulPayment['invoice_payload'];
        $amount = $successfulPayment['total_amount'];
        $telegramChargeId = $successfulPayment['telegram_payment_charge_id'];
        $providerChargeId = $successfulPayment['provider_payment_charge_id'] ?? null;

        Log::info('SuccessfulPayment received', [
            'user_id' => $userId,
            'payload' => $payload,
            'amount' => $amount,
            'telegram_charge_id' => $telegramChargeId,
        ]);

        // Проверяем, не была ли эта транзакция уже обработана
        $existingInvoice = $this->starInvoiceRepository->findByTelegramChargeId($telegramChargeId);
        if ($existingInvoice) {
            Log::warning('Duplicate payment detected', [
                'telegram_charge_id' => $telegramChargeId,
                'invoice_id' => $existingInvoice->id,
            ]);

            return false;
        }

        // Находим транзакцию по payload
        $invoice = $this->starInvoiceRepository->findByPayload($payload);

        if (!$invoice) {
            Log::error('Invoice not found for SuccessfulPayment', [
                'payload' => $payload,
                'telegram_charge_id' => $telegramChargeId,
            ]);

            return false;
        }

        // Обновляем транзакцию
        $updated = $this->starInvoiceRepository->update($invoice, [
            'status' => 'completed',
            'telegram_payment_charge_id' => $telegramChargeId,
            'provider_payment_charge_id' => $providerChargeId,
        ]);

        if ($updated) {
            Log::info('Invoice completed successfully', [
                'invoice_id' => $invoice->id,
                'telegram_charge_id' => $telegramChargeId,
            ]);

            // Здесь можно добавить логику для активации подписки пользователя
            $this->activateUserSubscription($userId, $amount);

            // Отправляем уведомление об успешной оплате
            $this->sendSuccessNotification($userId, $amount);

            return true;
        }

        return false;
    }

    /**
     * Активировать подписку пользователя
     *
     * @param int $userId
     * @param int $amount
     * @return void
     */
    private function activateUserSubscription(int $userId, int $amount): void
    {
        // Здесь будет реализована логика активации подписки
        // Например, продлить подписку на 1 месяц

        Log::info('User subscription activated', [
            'user_id' => $userId,
            'amount' => $amount,
        ]);
    }

    /**
     * Отправить уведомление об успешной оплате
     *
     * @param int $userId
     * @param int $amount
     * @return void
     */
    private function sendSuccessNotification(int $userId, int $amount): void
    {
        $message = sprintf(
            "✅ Оплата успешно завершена!\n\n" .
            "Списано: %d ⭐️\n" .
            "Ваша подписка активирована на 1 месяц.",
            $amount
        );

        $this->telegramApiService->sendMessageToChat($message);
    }
}
