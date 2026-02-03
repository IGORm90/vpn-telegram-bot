<?php

namespace App\Repositories;

use App\Models\VpnServer;
use App\Services\CacheService;
use Illuminate\Database\Eloquent\Collection;

class VpnServerRepository
{
    private const CACHE_KEY = 'vpn_servers:all';
    private const CACHE_TTL = 3600; // 1 час

    private CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Получить все серверы (с кэшированием)
     */
    public function getAll(): Collection
    {
        return $this->cacheService->remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn() => VpnServer::all()
        );
    }

    /**
     * Получить сервер по ID (из кэшированного списка)
     */
    public function findById(int $id): ?VpnServer
    {
        $servers = $this->getAll();
        
        return $servers->firstWhere('id', $id);
    }

    /**
     * Получить один сервер по стране (из кэшированного списка)
     */
    public function findByCountry(string $country): ?VpnServer
    {
        $servers = $this->getAll();
        
        return $servers->firstWhere('country', $country);
    }

    /**
     * Создать сервер
     */
    public function create(array $data): VpnServer
    {
        $server = VpnServer::create($data);
        
        // Очищаем все кэши серверов после создания
        $this->clearCache();
        
        return $server;
    }

    /**
     * Удалить сервер по ID
     */
    public function delete(int $id): bool
    {
        $server = $this->findById($id);
        
        if (!$server) {
            return false;
        }
        
        $deleted = $server->delete();
        
        // Очищаем кэш после удаления
        if ($deleted) {
            $this->clearCache();
        }
        
        return $deleted;
    }

    /**
     * Очистить кэш серверов
     */
    private function clearCache(): void
    {
        $this->cacheService->forget(self::CACHE_KEY);
    }
}
