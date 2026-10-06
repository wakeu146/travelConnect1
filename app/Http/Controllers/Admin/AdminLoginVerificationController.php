<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuthenticationService;
use App\Services\TotpAdminSecondFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminLoginVerificationController extends Controller
{
    public function show(Request $request, AdminAuthenticationService $adminAuthentication): View|RedirectResponse
    {
        $admin = $adminAuthentication->pendingAdmin($request);
        if (! $admin) {
            return redirect()->route('login');
        }

        return view('admin.auth.verify-code', [
            'admin' => $admin,
            'requiresAuthenticator' => $adminAuthentication->pendingFactorUsesAuthenticator($request),
        ]);
    }

    public function verify(
        Request $request,
        AdminAuthenticationService $adminAuthentication,
        TotpAdminSecondFactor $totp,
    ): RedirectResponse {
        $requiresAuthenticator = $adminAuthentication->pendingFactorUsesAuthenticator($request);
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                $requiresAuthenticator ? 'regex:/^[A-Za-z0-9-]+$/' : 'digits:6',
            ],
        ]);

        $deviceCookie = $adminAuthentication->verifyCode($request, $data['code']);
        if (! $deviceCookie) {
            return back()->withErrors(['code' => __('messages.admin_code_invalid')]);
        }

        $admin = $request->user();
        if (! $requiresAuthenticator && $admin instanceof User && ! $totp->isEnrolled($admin)) {
            return redirect()->route('admin.totp.setup')->withCookie($deviceCookie);
        }

        return redirect()->route('admin.dashboard')->withCookie($deviceCookie);
    }

    public function resend(Request $request, AdminAuthenticationService $adminAuthentication): RedirectResponse
    {
        try {
            if (! $adminAuthentication->resendCode($request)) {
                return redirect()->route('login');
            }
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()->withErrors(['code' => __('messages.admin_login_code_delivery_failed')]);
        }

        return back()->with('status', __('messages.admin_login_code_sent'));
    }
}
