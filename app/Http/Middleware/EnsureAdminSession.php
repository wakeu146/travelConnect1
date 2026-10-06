<?php

namespace App\Http\Middleware;

use App\Services\AdminAuthenticationService;
use App\Services\AdminLoginSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSession
{
    public function __construct(
        private readonly AdminAuthenticationService $adminAuthentication,
        private readonly AdminLoginSecurityService $adminSecurity,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if (! $user->isAdmin()) {
            abort(403);
        }

        $failureStep = null;
        if (! $this->adminAuthentication->isVerifiedSession($user, $request)) {
            $failureStep = 'second_factor';
        } elseif ($this->adminAuthentication->hasExpiredIdleSession($request)) {
            $failureStep = 'idle_timeout';
        }

        if ($failureStep !== null) {
            $this->adminSecurity->recordEvent($user, $user->email, $request, 'blocked', $failureStep);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        $this->adminAuthentication->touchAdminSession($request);

        return $next($request);
    }
}
