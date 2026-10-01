<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->isAdmin()) {
            Log::warning('Nem adminisztrátor felhasználó próbált adminisztrátori végpontot elérni.', [
                'user_id' => $user?->getAuthIdentifier(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ehhez a művelethez adminisztrátori jogosultság szükséges.',
            ], 403);
        }

        return $next($request);
    }
}
