<?php

namespace App\Services;

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

    public function createUser(string $username): ?array
    {
        try {
            $response = $this->httpService->post($this->baseUrl . '/api/users', [
                'username' => $username,
            ], [
                'Authorization' => 'Bearer ' . env('VPN_API_TOKEN'),
            ]);

            if ($response && $response['success']) {
                return $response['data'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to create user', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);
            return null;
        }

        return null;
    }

    public function getUserConfig(int $userId): ?array
    {
        try {
            $response = $this->httpService->get($this->baseUrl . "/api/users/$userId/config", [
                'Authorization' => 'Bearer ' . env('VPN_API_TOKEN'),
            ]);

            if ($response && $response['success']) {
                return $response['data'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get user config', [
                'message' => $e->getMessage(),
                'response' => $response,
            ]);

            return null;
        }

        return null;
    }
}