<?php

namespace App\Services;

use App\Models\User;
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

    public function createUser(User $user): ?array
    {
        $response = null;
        
        try {
            $response = $this->httpService->post($this->baseUrl . '/api/users', [
                'username' => $user->telegram_username,
                'is_active' => $user->is_active,
                'expires_at' => $user->expires_at->toIso8601ZuluString(),
            ], [
                'Authorization' => 'Bearer ' . env('VPN_API_TOKEN'),
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

    public function getUserConfig(User $user): ?array
    {
        $response = null;
        
        try {
            $response = $this->httpService->get(
                $this->baseUrl . "/api/users/$user->vpn_id/config",
                [],
                [
                    'Authorization' => 'Bearer ' . env('VPN_API_TOKEN'),
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

    public function setUserActive(User $user): ?bool
    {
        $response = null;

        try {
            $response = $this->httpService->patch(
                $this->baseUrl . "/api/users/$user->vpn_id",
                [
                    'is_active' => $user->is_active,
                    'expires_at' => $user->expires_at->toIso8601ZuluString(),
                ],
                [
                    'Authorization' => 'Bearer ' . env('VPN_API_TOKEN'),
                ]
            );

            if ($response && $response['success'] && isset($response['data']['is_active'])) {
                return $response['data']['is_active'];
            }
            
            Log::warning('VPN API returned unexpected set active response', [
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to set user active status', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);

            return null;
        }

        return null;
    }
}
