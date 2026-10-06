<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmAdminTotpEnrollmentRequest;
use App\Models\User;
use App\Services\TotpAdminSecondFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTotpEnrollmentController extends Controller
{
    public function show(Request $request, TotpAdminSecondFactor $totp): View|RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User && $admin->isAdmin(), 403);

        $recoveryCodes = $request->session()->get('admin_totp_recovery_codes');
        if (is_array($recoveryCodes) && $recoveryCodes !== []) {
            return view('admin.auth.totp-recovery-codes', ['recoveryCodes' => $recoveryCodes]);
        }

        if ($totp->isEnrolled($admin)) {
            return redirect()->route('admin.dashboard');
        }

        $credential = $totp->prepareEnrollment($admin);

        return view('admin.auth.totp-setup', [
            'qrCode' => $totp->qrCodeInline($admin, $credential),
            'secret' => $credential->secret,
        ]);
    }

    public function confirm(
        ConfirmAdminTotpEnrollmentRequest $request,
        TotpAdminSecondFactor $totp,
    ): RedirectResponse {
        $admin = $request->user();
        abort_unless($admin instanceof User && $admin->isAdmin(), 403);

        $recoveryCodes = $totp->confirmEnrollment($admin, $request->validated('code'));
        if (! $recoveryCodes) {
            return back()->withErrors(['code' => __('messages.admin_totp_code_invalid')]);
        }

        return redirect()->route('admin.totp.setup')
            ->with('admin_totp_recovery_codes', $recoveryCodes);
    }

    public function skip(): RedirectResponse
    {
        return redirect()->route('admin.dashboard');
    }
}
