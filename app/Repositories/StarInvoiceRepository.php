<?php

namespace App\Repositories;

use App\Models\StarInvoice;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class StarInvoiceRepository
{
    /**
     * Найти транзакцию по ID
     */
    public function findById(int $id): ?StarInvoice
    {
        return StarInvoice::find($id);
    }

    /**
     * Найти транзакцию по StarInvoice_id платежной системы
     */
    public function findByStarInvoiceId(string $starInvoiceId): ?StarInvoice
    {
        return StarInvoice::where('StarInvoice_id', $starInvoiceId)->first();
    }

    /**
     * Создать новую транзакцию
     */
    public function create(int $userId, int $amount): StarInvoice
    {
        $payload = $this->generatePayload($userId);

        $data = [
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => 'XTR',
            'status' => 'pending',
            'payload' => $payload,
        ];

        return StarInvoice::create($data);
    }

    /**
     * Обновить транзакцию
     */
    public function update(StarInvoice $starInvoice, array $data): bool
    {
        return $starInvoice->update($data);
    }

    /**
     * Получить все транзакции пользователя
     */
    public function getByUserId(int $userId): Collection
    {
        return StarInvoice::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Получить транзакции пользователя за период
     */
    public function getByUserIdAndPeriod(int $userId, Carbon $from, Carbon $to): Collection
    {
        return StarInvoice::where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Получить последнюю транзакцию пользователя
     */
    public function getLastByUserId(int $userId): ?StarInvoice
    {
        return StarInvoice::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Найти транзакцию по telegram_payment_charge_id
     */
    public function findByTelegramChargeId(string $telegramChargeId): ?StarInvoice
    {
        return StarInvoice::where('telegram_payment_charge_id', $telegramChargeId)->first();
    }

    /**
     * Найти транзакцию по payload
     */
    public function findByPayload(string $payload): ?StarInvoice
    {
        return StarInvoice::where('payload', $payload)->first();
    }

    /**
     * Генерировать уникальный payload для транзакции
     *
     * @param int $userId
     * @return string
     */
    private function generatePayload(int $userId): string
    {
        return sprintf('user_%d_order_%s', $userId, uniqid());
    }

}
