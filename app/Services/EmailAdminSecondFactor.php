<?php

namespace App\Services;

use App\Contracts\AdminSecondFactor;
use App\Mail\AdminLoginCodeMail;
use App\Models\AdminLoginChallenge;
use App\Models\IntegrationFailure;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailAdminSecondFactor implements AdminSecondFactor
{
    public function usesAuthenticator(User $admin): bool
    {
        return false;
    }

    public function issue(User $admin, string $sessionKey): AdminLoginChallenge
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = AdminLoginChallenge::query()->firstOrNew([
            'session_hash' => hash('sha256', $sessionKey),
        ]);
        $attempts = $challenge->exists ? $challenge->attempts : 0;
        $challenge->fill([
            'user_id' => $admin->id,
            'code_hash' => Hash::make($code),
            'attempts' => $attempts,
            'expires_at' => now()->addMinutes(config('admin.login_code_expires_minutes')),
            'consumed_at' => null,
            'invalidated_at' => null,
        ])->save();

        try {
            Mail::to($admin->email)->send(new AdminLoginCodeMail(
                $admin->name,
                $code,
                (int) config('admin.login_code_expires_minutes'),
            ));
        } catch (Throwable $exception) {
            IntegrationFailure::query()->create([
                'integration' => 'email',
                'operation' => 'admin_second_factor_delivery',
                'safe_message' => __('messages.admin_alerts.integration_messages.email_delivery_failure'),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }

        return $challenge;
    }

    public function verify(User $admin, AdminLoginChallenge $challenge, string $proof): bool
    {
        return $challenge->user_id === $admin->id
            && $challenge->consumed_at === null
            && $challenge->invalidated_at === null
            && $challenge->attempts < (int) config('admin.login_code_max_attempts')
            && $challenge->expires_at->isFuture()
            && Hash::check($proof, $challenge->code_hash);
    }
}
