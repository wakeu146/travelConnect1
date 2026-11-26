<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OAuthResponseDiagnostics
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

        if (! in_array($routeName, self::TRACKED_ROUTES, true)) {
            return $next($request);
        }

        $diagnosticId = (string) Str::uuid();
        $request->attributes->set('oauth_diagnostic_id', $diagnosticId);

        $response = $next($request);
        $location = $response->headers->get('Location');
        $sessionCookieName = (string) config('session.cookie');
        $sessionCookieSet = collect($response->headers->getCookies())
            ->contains(fn ($cookie): bool => $cookie->getName() === $sessionCookieName);

        Log::info('OAuth response diagnostic: outgoing response', [
            'diagnostic_id' => $diagnosticId,
            'route' => $routeName,
            'status' => $response->getStatusCode(),
            'is_redirect' => $response->isRedirect(),
            'location_present' => $location !== null,
            'location_host' => $location === null ? null : parse_url($location, PHP_URL_HOST),
            'session_cookie_set' => $sessionCookieSet,
            'session_driver' => config('session.driver'),
            'session_secure' => config('session.secure'),
            'session_domain_configured' => (bool) config('session.domain'),
        ]);

        return $response;
    }
}
