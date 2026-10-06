<?php

namespace App\Services;

use App\Contracts\AdminSecondFactor;
use App\Models\AdminLoginChallenge;
use App\Models\AdminTotpCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;
use PragmaRX\Google2FAQRCode\QRCode\Chillerlan;

class TotpAdminSecondFactor implements AdminSecondFactor
{
    private const RECOVERY_CODE_COUNT = 10;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function usesAuthenticator(User $admin): bool
    {
        return $this->isEnrolled($admin);
    }

    public function isEnrolled(User $admin): bool
    {
        return $admin->adminTotpCredential()->whereNotNull('verified_at')->exists();
    }

    public function prepareEnrollment(User $admin): AdminTotpCredential
    {
        abort_unless($admin->isAdmin(), 403);

        $credential = $admin->adminTotpCredential()->first();
        if ($credential?->verified_at) {
            abort(409);
        }

        if (! $credential) {
            $credential = $admin->adminTotpCredential()->create([
                'secret' => $this->google2fa->generateSecretKey(32),
            ]);
        }

        return $credential;
    }

    public function qrCodeInline(User $admin, AdminTotpCredential $credential): string
    {
        $qrCode = new Google2FAQRCode(new Chillerlan);

        return $qrCode->getQRCodeInline(
            (string) config('app.name'),
            $admin->email,
            $credential->secret,
            240,
        );
    }

    /** @return array<int, string>|null */
    public function confirmEnrollment(User $admin, string $proof): ?array
    {
        return DB::transaction(function () use ($admin, $proof): ?array {
            $credential = $admin->adminTotpCredential()->lockForUpdate()->first();
            if (! $credential || $credential->verified_at) {
                return null;
            }

            $verifiedCounter = $this->google2fa->verifyKeyNewer(
                $credential->secret,
                $proof,
                $credential->last_used_counter ?? 0,
                1,
            );
            if (! is_int($verifiedCounter)) {
                return null;
            }

            $recoveryCodes = $this->newRecoveryCodes();
            $credential->forceFill([
                'verified_at' => now(),
                'last_used_counter' => $verifiedCounter,
                'recovery_code_hashes' => array_map(
                    fn (string $code): string => Hash::make($this->normalizeRecoveryCode($code)),
                    $recoveryCodes,
                ),
            ])->save();

            return $recoveryCodes;
        });
    }

    public function issue(User $admin, string $sessionKey): AdminLoginChallenge
    {
        $challenge = AdminLoginChallenge::query()->firstOrNew([
            'session_hash' => hash('sha256', $sessionKey),
        ]);
        $challenge->fill([
            'user_id' => $admin->id,
            'code_hash' => Hash::make(Str::random(64)),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('admin.idle_timeout_minutes')),
            'consumed_at' => null,
            'invalidated_at' => null,
        ])->save();

        return $challenge;
    }

    public function verify(User $admin, AdminLoginChallenge $challenge, string $proof): bool
    {
        if ($challenge->user_id !== $admin->id
            || $challenge->consumed_at
            || $challenge->invalidated_at
            || $challenge->expires_at->isPast()) {
            return false;
        }

        $credential = $admin->adminTotpCredential()->lockForUpdate()->first();
        if (! $credential?->verified_at) {
            return false;
        }

        if (preg_match('/^\d{6}$/', $proof) === 1) {
            $verifiedCounter = $this->google2fa->verifyKeyNewer(
                $credential->secret,
                $proof,
                $credential->last_used_counter ?? 0,
                1,
            );
            if (is_int($verifiedCounter)) {
                $credential->forceFill([
                    'last_used_counter' => $verifiedCounter,
                ])->save();

                return true;
            }
        }

        $recoveryCode = $this->normalizeRecoveryCode($proof);
        $recoveryCodeHashes = $credential->recovery_code_hashes ?? [];
        foreach ($recoveryCodeHashes as $index => $recoveryCodeHash) {
            if (! Hash::check($recoveryCode, $recoveryCodeHash)) {
                continue;
            }

            unset($recoveryCodeHashes[$index]);
            $credential->forceFill([
                'recovery_code_hashes' => array_values($recoveryCodeHashes),
            ])->save();

            return true;
        }

        return false;
    }

    /** @return array<int, string> */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        for ($index = 0; $index < self::RECOVERY_CODE_COUNT; $index++) {
            $rawCode = Str::upper(Str::random(12));
            $codes[] = implode('-', str_split($rawCode, 4));
        }

        return $codes;
    }

    private function normalizeRecoveryCode(string $code): string
    {
        return preg_replace('/[^A-Z0-9]/', '', Str::upper(trim($code))) ?? '';
    }
}
