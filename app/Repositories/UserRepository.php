<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * Найти пользователя по Telegram ID
     */
    public function findByTelegramId(int $telegramId): ?User
    {
        return User::where('telegram_id', $telegramId)->first();
    }

    /**
     * Найти пользователя по VPN ID
     */
    public function findByVpnId(int $vpnId): ?User
    {
        return User::where('vpn_id', $vpnId)->first();
    }

    /**
     * Получить пользователя по ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Создать нового пользователя
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Обновить данные пользователя
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    /**
     * Удалить пользователя
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Получить всех активных пользователей
     */
    public function getActiveUsers(): Collection
    {
        return User::where('is_active', true)->get();
    }

    /**
     * Получить пользователей с истекающей подпиской
     */
    public function getUsersWithExpiringSubscription(\DateTime $before): Collection
    {
        return User::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $before)
            ->get();
    }

    /**
     * Активировать пользователя
     */
    public function activate(User $user): bool
    {
        return $user->update(['is_active' => true]);
    }

    /**
     * Деактивировать пользователя
     */
    public function deactivate(User $user): bool
    {
        return $user->update(['is_active' => false]);
    }

    /**
     * Проверить существование пользователя по Telegram ID
     */
    public function existsByTelegramId(int $telegramId): bool
    {
        return User::where('telegram_id', $telegramId)->exists();
    }

    /**
     * Обновить дату истечения подписки
     */
    public function updateExpiration(User $user, \DateTime $expiresAt): bool
    {
        return $user->update(['expires_at' => $expiresAt]);
    }
}

