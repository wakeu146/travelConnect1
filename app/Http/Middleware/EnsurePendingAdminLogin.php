<?php

namespace App\Http\Middleware;

use App\Services\AdminAuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePendingAdminLogin
{
    public function __construct(private readonly AdminAuthenticationService $adminAuthentication) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAdmin()) {
            abort(403);
        }

        if ($user && $this->adminAuthentication->isVerifiedSession($user, $request)) {
            return redirect()->route('admin.dashboard');
        }

        if ($user) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if (! $this->adminAuthentication->pendingAdmin($request)) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
