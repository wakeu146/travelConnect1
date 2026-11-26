<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OAuthSessionDiagnostics
{
    private const TRACKED_ROUTES = [
        'login',
        'google.redirect',
        'google.agency.redirect',
        'google.callback',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (in_array($routeName, self::TRACKED_ROUTES, true) && $request->hasSession()) {
            Log::info('OAuth response diagnostic: session before controller', [
                'diagnostic_id' => $request->attributes->get('oauth_diagnostic_id'),
                'route' => $routeName,
                'session_driver' => config('session.driver'),
                'incoming_session_cookie' => $request->cookies->has((string) config('session.cookie')),
                'session_state_present' => $request->session()->has('state'),
                'callback_state_query_present' => $request->query->has('state'),
                'callback_code_query_present' => $request->query->has('code'),
            ]);
        }

        return $next($request);
    }
}
