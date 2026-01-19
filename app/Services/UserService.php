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
                $vpnUser = $this->vpnApiService->createUser($username);
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
            
            // тут нужно проверить наличие uri в поле settings
            if (!empty($user->settings) && is_array($user->settings) && !empty($user->settings['uri'])) {
                return $user->settings['uri'] ?? null;
            }


            // Получаем конфигурацию пользователя
            $config = $this->vpnApiService->getUserConfig($vpnUser['id']);
            if (!$config) {
                Log::error('Failed to get VPN user config', [
                    'telegram_id' => $telegramId,
                    'vpn_id' => $vpnUser['id'],
                ]);
                return null;
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
}

