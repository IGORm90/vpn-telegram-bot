<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\VpnApiService;
use Illuminate\Support\Facades\Log;

class ActivateVpnJob extends Job
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
     * Активировать пользователя на всех VPN серверах
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('ActivateVpnJob: user not found', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        $vpnApiService = new VpnApiService();
        $result = $vpnApiService->setUserActive($user, true);

        if (!$result) {
            Log::error('ActivateVpnJob: failed to activate user on VPN servers', [
                'user_id' => $this->userId,
                'telegram_id' => $user->telegram_id,
            ]);
        }
    }
}
