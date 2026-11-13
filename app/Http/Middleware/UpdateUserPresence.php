<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserPresence
{
    private const ACTIVITY_REFRESH_INTERVAL_MINUTES = 1;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (
            ! $user->is_online
            || ! $user->last_active_at
            || $user->last_active_at->lte(now()->subMinutes(self::ACTIVITY_REFRESH_INTERVAL_MINUTES))
        )) {
            $user->forceFill([
                'is_online' => true,
                'last_active_at' => now(),
            ])->saveQuietly();
        }

        return $next($request);
    }
}
