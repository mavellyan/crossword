<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Ehhez a művelethez adminisztrátori jogosultság szükséges.',
            ], 403);
        }

        return $next($request);
    }
}