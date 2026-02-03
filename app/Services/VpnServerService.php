<?php

namespace App\Services;

use App\Models\VpnServer;
use Illuminate\Support\Facades\Log;
use App\Repositories\VpnServerRepository;
use Illuminate\Database\Eloquent\Collection;

class VpnServerService
{
    private VpnServerRepository $vpnServerRepository;

    public function __construct()
    {
        $cacheService = new CacheService();
        $this->vpnServerRepository = new VpnServerRepository($cacheService);
    }

    /**
     * Получить все серверы
     */
    public function getAllServers(): Collection
    {
        return $this->vpnServerRepository->getAll();
    }

    /**
     * Получить сервер по ID
     */
    public function getServerById(int $id): ?VpnServer
    {
        return $this->vpnServerRepository->findById($id);
    }

    /**
     * Получить сервер по стране
     */
    public function getServerByCountry(string $country): ?VpnServer
    {
        return $this->vpnServerRepository->findByCountry($country);
    }

    /**
     * Создать новый сервер
     */
    public function createServer(array $data): ?VpnServer
    {
        try {
            // Валидация данных
            if (empty($data['vpn_url']) || empty($data['bearer_token']) || empty($data['country']) || empty($data['title']) || empty($data['protocol'])) {
                Log::error('Invalid server data', ['data' => $data]);
                return null;
            }

            // Проверка, что сервер с таким IP еще не существует
            $existingServer = $this->getAllServers()->firstWhere('vpn_url', $data['vpn_url']);
            if ($existingServer) {
                Log::warning('Server with this vpn_url already exists', ['vpn_url' => $data['vpn_url']]);
                return null;
            }

            return $this->vpnServerRepository->create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create VPN server', [
                'data' => $data,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Удалить сервер по ID
     */
    public function deleteServer(int $id): bool
    {
        try {
            return $this->vpnServerRepository->delete($id);
        } catch (\Exception $e) {
            Log::error('Failed to delete VPN server', [
                'id' => $id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получить случайный сервер
     */
    public function getRandomServer(): ?VpnServer
    {
        $servers = $this->getAllServers();

        if ($servers->isEmpty()) {
            return null;
        }

        return $servers->random();
    }

    /**
     * Проверить доступность сервера по ID
     */
    public function isServerAvailable(int $id): bool
    {
        $server = $this->getServerById($id);
        return $server !== null;
    }
}
