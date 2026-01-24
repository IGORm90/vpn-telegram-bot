<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class BearerTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Bearer token is required',
            ], 401);
        }

        $validToken = config('app.api_auth_token');

        if (!$validToken || $token !== $validToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid token',
            ], 401);
        }

        return $next($request);
    }
}
