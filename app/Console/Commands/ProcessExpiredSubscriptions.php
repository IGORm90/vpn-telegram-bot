<?php

namespace App\Console\Commands;

use App\Jobs\DeactivateVpnJob;
use App\Jobs\SendSubscriptionExpiredMessage;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:process-expired';
    protected $description = 'Деактивировать пользователей с истекшей подпиской';

    public function handle(): void
    {
        $userRepository = new UserRepository();
        $expiredUsers = $userRepository->getExpiredActiveUsers();

        if ($expiredUsers->isEmpty()) {
            $this->info('Нет пользователей с истекшей подпиской.');
            return;
        }

        $this->info("Найдено {$expiredUsers->count()} пользователей с истекшей подпиской.");

        $processed = 0;

        foreach ($expiredUsers as $user) {
            try {
                DB::transaction(function () use ($user, $userRepository) {
                    // 1. Деактивируем пользователя в БД
                    $userRepository->deactivate($user);

                    // 2. Задача на отключение VPN на серверах
                    dispatch(new DeactivateVpnJob($user->id));

                    // 3. Задача на отправку сообщения пользователю
                    dispatch(new SendSubscriptionExpiredMessage($user->telegram_id));
                });

                $processed++;

                Log::info('Expired subscription processed', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'expires_at' => $user->expires_at,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to process expired subscription', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Ошибка при обработке пользователя {$user->telegram_id}: {$e->getMessage()}");
            }
        }

        $this->info("Обработано: {$processed} из {$expiredUsers->count()}.");
    }
}
