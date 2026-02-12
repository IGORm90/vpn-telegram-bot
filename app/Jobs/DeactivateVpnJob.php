<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\VpnApiService;
use Illuminate\Support\Facades\Log;

class DeactivateVpnJob extends Job
{
    /**
     * Количество попыток выполнения задачи
     */
    public int $tries = 3;

    /**
     * @param int $userId ID пользователя
     */
    public function __construct(
        private int $userId
    ) {}

    /**
     * Отключить пользователя на всех VPN серверах
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('DeactivateVpnJob: user not found', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        $vpnApiService = new VpnApiService();
        $result = $vpnApiService->setUserActive($user, false);

        if (!$result) {
            Log::error('DeactivateVpnJob: failed to deactivate user on VPN servers', [
                'user_id' => $this->userId,
                'telegram_id' => $user->telegram_id,
            ]);
        }
    }
}
