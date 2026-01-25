<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Repositories\UserRepository;

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
    public function createUserConfig(int $telegramId, string $username): ?string
    {
        try {
            // Создаем пользователя в БД
            $user = $this->userRepository->getOrCreate([
                'telegram_id' => $telegramId,
                'telegram_username' => $username,
                'is_active' => true,
                'expires_at' => Carbon::now()->addDays(14),
            ]);


            if ($user->vpn_id === null) {
                $vpnUser = $this->vpnApiService->createUser($user);
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
            $config = $this->vpnApiService->getUserConfig($user);
            if (!$config) {
                $vpnUser = $this->vpnApiService->createUser($user);

                if (!$vpnUser) {
                    Log::error('Failed to create VPN user', [
                        'telegram_id' => $telegramId,
                        'username' => $username,
                    ]);

                    return null;
                }

                $this->userRepository->update($user, [
                    'vpn_id' => $vpnUser['id'],
                ]);
            }

            // Сохраняем конфигурацию в БД
            $this->userRepository->update($user, [
                'settings' => $config,
            ]);

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
    public function updateUser(int $id, array $data): ?\App\Models\User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            return null;
        }

        $this->userRepository->update($user, $data);
        
        $this->vpnApiService->setUserActive($user);

        return $user->fresh();
    }
}

