<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;

class UserService
{
    private UserRepository $userRepository;
    private VpnApiService $vpnApiService;

    public function __construct(UserRepository $userRepository, VpnApiService $vpnApiService)
    {
        $this->userRepository = $userRepository;
        $this->vpnApiService = $vpnApiService;
    }

    /**
     * Создать конфигурацию пользователя
     */
    public function createUserConfig(int $telegramId, string $username): ?string
    {
        try {
            // Создаем пользователя в БД
            $user = $this->userRepository->create([
                'telegram_id' => $telegramId,
                'telegram_username' => $username,
                'is_active' => true,
            ]);

            // Создаем пользователя в VPN API
            $vpnUser = $this->vpnApiService->createUser($username);
            if (!$vpnUser) {
                Log::error('Failed to create VPN user', [
                    'telegram_id' => $telegramId,
                    'username' => $username,
                ]);
                $this->userRepository->delete($user);
                return null;
            }

            // Обновляем VPN ID в БД
            $this->userRepository->update($user, [
                'vpn_id' => $vpnUser['id'],
            ]);

            // Получаем конфигурацию пользователя
            $config = $this->vpnApiService->getUserConfig($vpnUser['id']);
            if (!$config) {
                Log::error('Failed to get VPN user config', [
                    'telegram_id' => $telegramId,
                    'vpn_id' => $vpnUser['id'],
                ]);
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
}

