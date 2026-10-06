<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Signature('admin:reset')]
#[Description('Reset an administrator account interactively and revoke its sessions and devices.')]
class AdminResetCommand extends Command
{
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error(__('messages.admin_command_interactive_only'));

            return self::FAILURE;
        }

        $targetEmail = Str::lower(trim((string) $this->ask(__('messages.admin_reset_target_prompt'))));
        $admin = User::query()->where('email', $targetEmail)->where('role', UserRole::ADMIN)->first();

        if (! $admin) {
            $this->error(__('messages.admin_not_found'));

            return self::FAILURE;
        }

        $newEmail = Str::lower(trim((string) $this->ask(__('messages.admin_email_prompt'), $admin->email)));
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false
            || User::withTrashed()->where('email', $newEmail)->where('id', '!=', $admin->id)->exists()) {
            $this->error(__('messages.admin_command_invalid_identity'));

            return self::FAILURE;
        }

        $password = (string) $this->secret(__('messages.admin_password_prompt'));
        $passwordConfirmation = (string) $this->secret(__('messages.admin_password_confirm_prompt'));
        if (strlen($password) < 12 || ! hash_equals($password, $passwordConfirmation)) {
            $this->error(__('messages.admin_password_invalid'));

            return self::FAILURE;
        }

        DB::transaction(function () use ($admin, $newEmail, $password): void {
            $admin->forceFill([
                'email' => $newEmail,
                'password' => $password,
                'remember_token' => Str::random(60),
                'admin_session_version' => $admin->admin_session_version + 1,
            ])->save();
            $admin->adminLoginChallenges()->delete();
            $admin->adminTotpCredential()->delete();
            $admin->trustedAdminDevices()->delete();
            $admin->adminLoginEvents()->create([
                'email' => $newEmail,
                'outcome' => 'blocked',
                'failed_step' => 'admin_reset',
            ]);

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $admin->id)->delete();
            }
        });

        Log::notice('Admin credentials reset using the admin:reset command.', [
            'admin_id' => $admin->id,
            'email_changed' => $targetEmail !== $newEmail,
        ]);

        $this->info(__('messages.admin_reset_completed'));

        return self::SUCCESS;
    }
}
