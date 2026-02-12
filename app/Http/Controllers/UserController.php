<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Repositories\UserRepository;
use App\Services\UserService;

class UserController extends Controller
{
    private UserRepository $userRepository;
    private UserService $userService;

    public function __construct(UserRepository $userRepository, UserService $userService)
    {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
    }

    /**
     * Получить список пользователей с пагинацией и фильтрацией
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $telegramUsername = $request->query('telegram_username');

        $users = $this->userRepository->getPaginated(
            perPage: 30,
            telegramUsername: $telegramUsername
        );

        return response()->json($users);
    }

    /**
     * Обновить данные пользователя
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $this->validate($request, [
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date',
            'balance' => 'sometimes|numeric|min:0',
        ]);

        $data = $request->only(['is_active', 'expires_at', 'balance']);
        $user = $this->userService->updateUser($id, $data);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($user);
    }
}
