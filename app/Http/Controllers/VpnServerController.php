<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\VpnServerService;

class VpnServerController extends Controller
{
    private VpnServerService $vpnServerService;

    public function __construct(VpnServerService $vpnServerService)
    {
        $this->vpnServerService = $vpnServerService;
    }

    /**
     * Получить серверы с возможностью фильтрации по id или country
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Фильтрация по ID
        if ($request->has('id')) {
            $server = $this->vpnServerService->getServerById((int)$request->query('id'));
            
            if (!$server) {
                return response()->json(['error' => 'Server not found'], 404);
            }
            
            return response()->json($server);
        }

        // Фильтрация по стране
        if ($request->has('country')) {
            $server = $this->vpnServerService->getServerByCountry($request->query('country'));
            
            if (!$server) {
                return response()->json(['error' => 'Server not found'], 404);
            }
            
            return response()->json($server);
        }

        // Получить все серверы
        $servers = $this->vpnServerService->getAllServers();
        
        return response()->json($servers);
    }

    /**
     * Создать новый сервер
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'vpn_url' => 'required|string|max:45',
            'title' => 'required|string|max:128',
            'bearer_token' => 'required|string|max:512',
            'country' => 'required|string|size:2',
            'protocol' => 'required|string|max:20',
        ]);

        $data = $request->only(['vpn_url', 'bearer_token', 'country', 'title', 'protocol']);
        $server = $this->vpnServerService->createServer($data);

        if (!$server) {
            return response()->json(['error' => 'Failed to create server'], 400);
        }

        return response()->json($server, 201);
    }

    /**
     * Удалить сервер
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->vpnServerService->deleteServer($id);

        if (!$deleted) {
            return response()->json(['error' => 'Server not found'], 404);
        }

        return response()->json(['message' => 'Server deleted successfully']);
    }
}
