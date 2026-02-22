<?php

namespace App\Services;

use App\Models\VpnServer;
use Illuminate\Support\Facades\Log;
use App\Repositories\UserRepository;
use App\Models\User;

class UserService
{
    private UserRepository $userRepository;
    private VpnApiService $vpnApiService;
    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->vpnApiService = new VpnApiService();
    }

    /**
     * Создать конфигурацию пользователя
     */
    public function getUserConfig(int $telegramId, VpnServer $server, string $username): ?string
    {
        try {
            $user = $this->userRepository->getOrCreate($telegramId, $username);

            if ($user->vpn_id === null) {
                $vpnUser = $this->vpnApiService->createUser($user, $server);
                if (!$vpnUser) {
                    Log::error('Failed to create VPN user', [
                        'telegram_id' => $telegramId,
                        'username' => $username,
                    ]);

                    return null;
                }

                // Обновляем VPN ID в БД
                $this->userRepository->update($user, [
                    'vpn_id' => $vpnUser['id'],
                ]);
            }
            
            // Получаем конфигурацию пользователя
            $config = $this->vpnApiService->getUserConfig($user, $server);
            if (!$config) {
                $vpnUser = $this->vpnApiService->createUser($user, $server);

                if (!$vpnUser) {
                    Log::error('Failed to create VPN user', [
                        'telegram_id' => $telegramId,
                        'username' => $username,
                    ]);

                    return null;
                }

                $config = $this->vpnApiService->getUserConfig($user, $server);
            }

            if (!$config) {
                return null;
            }

            return $config['uri'];
        } catch (\Exception $e) {
            Log::error('Failed to create user config', [
                'telegram_id' => $telegramId,
                'username' => $username,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Обновить данные пользователя по ID
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\User|null
     */
    public function updateUser(int $id, array $data): ?User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return null;
        }

        $this->userRepository->update($user, $data);
        
        if (isset($data['is_active']) && $data['is_active'] !== $user->is_active) {
            $this->vpnApiService->setUserActive($user, $data['is_active']);
        }

        return $user->fresh();
    }
}

