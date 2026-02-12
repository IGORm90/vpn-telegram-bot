<?php

namespace App\Services;

use App\Models\User;
use App\Models\VpnServer;
use Illuminate\Support\Facades\Log;

class VpnApiService
{
    private string $baseUrl;
    private HttpService $httpService;
    
    public function __construct()
    {
        $this->baseUrl = env('VPN_API_BASE_URL');
        $this->httpService = new HttpService();
    }

    public function createUser(User $user, VpnServer $server): ?array
    {
        $response = null;
        
        try {
            $response = $this->httpService->post($server->vpn_url . '/api/users', [
                'id' => $user->telegram_id,
                'username' => $user->telegram_username,
                'is_active' => $user->is_active,
            ], [
                'Authorization' => 'Bearer ' . $server->bearer_token,
            ]);

            if ($response && $response['success'] && isset($response['data']['data'])) {
                return $response['data']['data'];
            }
            
            Log::warning('VPN API returned unexpected response', [
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);
            return null;
        }

        return null;
    }

    public function getUserConfig(User $user, VpnServer $server): ?array
    {
        $response = null;
        
        try {
            $response = $this->httpService->get(
                $server->vpn_url . "/api/users/$user->telegram_id/config",
                [],
                [
                    'Authorization' => 'Bearer ' . $server->bearer_token,
                ]
            );

            if ($response && $response['success'] && isset($response['data']['data'])) {
                return $response['data']['data'];
            }
            
            Log::warning('VPN API returned unexpected config response', [
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user config', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);

            return null;
        }

        return null;
    }

    public function setUserActive(User $user, bool|null $isActive): bool
    {
        if ($isActive === null) {
            $isActive = $user->is_active;
        }

        $servers = VpnServer::all();

        if ($servers->isEmpty()) {
            Log::warning('No VPN servers found in database');
            return false;
        }

        $allSuccessful = true;

        foreach ($servers as $server) {
            $response = null;

            try {
                $response = $this->httpService->patch(
                    $server->vpn_url . "/api/users/$user->telegram_id",
                    [
                        'is_active' => $isActive,
                    ],
                    [
                        'Authorization' => 'Bearer ' . $server->bearer_token,
                    ]
                );

                if ($response && $response['success'] && isset($response['data']['is_active'])) {
                    continue;
                }

                if ($response && !$response['success'] && $response['status'] === 404) {
                    continue;
                }

                Log::warning('VPN API returned unexpected set active response', [
                    'server_id' => $server->id,
                    'vpn_url' => $server->vpn_url,
                    'response' => $response,
                ]);

                $allSuccessful = false;
            } catch (\Exception $e) {
                Log::error('Failed to set user active status', [
                    'server_id' => $server->id,
                    'vpn_url' => $server->vpn_url,
                    'message' => $e->getMessage(),
                    'response' => $response,
                ]);

                $allSuccessful = false;
            }
        }

        return $allSuccessful;
    }
}
