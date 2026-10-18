<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\AdminAccountSecurityMail;
use App\Models\AdminUserActivity;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminUserManagementService
{
    public function deactivate(User $actor, int $userId, string $reason): bool
    {
        $this->assertAdministrator($actor);

        $user = DB::transaction(function () use ($actor, $userId, $reason): User {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            if ($user->trashed()) {
                throw ValidationException::withMessages([
                    'user' => __('messages.admin_users.already_deactivated'),
                ]);
            }

            $this->revokeDatabaseSessions($user);
            $user->delete();

            $this->recordActivity($user, $actor, 'deactivated', 'success', $reason);

            return $user;
        });

        return $this->sendSecurityEmail($user, $actor, 'account_deactivated');
    }

    public function revokeSessions(User $actor, int $userId, string $reason): bool
    {
        $this->assertAdministrator($actor);

        $user = DB::transaction(function () use ($actor, $userId, $reason): User {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            if ($user->trashed()) {
                throw ValidationException::withMessages([
                    'user' => __('messages.admin_users.already_deactivated'),
                ]);
            }

            $this->revokeDatabaseSessions($user);

            $this->recordActivity($user, $actor, 'sessions_revoked', 'success', $reason);

            return $user;
        });

        return $this->sendSecurityEmail($user, $actor, 'sessions_revoked');
    }

    public function restore(User $actor, int $userId, string $reason): bool
    {
        $this->assertAdministrator($actor);

        $user = DB::transaction(function () use ($actor, $userId, $reason): User {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            if (! $user->trashed()) {
                throw ValidationException::withMessages([
                    'user' => __('messages.admin_users.already_active'),
                ]);
            }

            $user->restore();

            $this->recordActivity($user, $actor, 'restored', 'success', $reason);

            return $user;
        });

        return $this->sendSecurityEmail($user, $actor, 'account_restored');
    }

    public function addNote(User $actor, int $userId, string $note): void
    {
        $this->assertAdministrator($actor);

        $user = User::withTrashed()->findOrFail($userId);

        $this->recordActivity($user, $actor, 'note_added', 'success', note: $note);
    }

    public function sendPasswordResetInstructions(User $actor, int $userId): bool
    {
        $this->assertAdministrator($actor);

        $user = User::query()->findOrFail($userId);
        $this->assertManageableAccount($user);

        $email = Str::lower($user->email);
        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_codes')->updateOrInsert(
            ['email' => $email],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        try {
            $user->notify(new PasswordResetCode($code));
        } catch (TransportExceptionInterface $exception) {
            DB::table('password_reset_codes')->where('email', $email)->delete();
            Log::warning('Administrator-triggered password reset email could not be delivered.', [
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'exception' => $exception,
            ]);
            $this->recordActivity($user, $actor, 'password_reset_requested', 'failed');

            return false;
        }

        $this->recordActivity($user, $actor, 'password_reset_requested', 'success');

        return true;
    }

    private function assertAdministrator(User $actor): void
    {
        abort_unless($actor->isAdmin(), 403);
    }

    private function assertManageableAccount(User $user): void
    {
        if ($user->role === UserRole::ADMIN) {
            abort(403, __('messages.admin_users.protected_administrator'));
        }
    }

    private function revokeDatabaseSessions(User $user): int
    {
        if (config('session.driver') !== 'database') {
            throw ValidationException::withMessages([
                'user' => __('messages.admin_users.database_sessions_required'),
            ]);
        }

        $table = (string) config('session.table');
        $connection = DB::connection(config('session.connection'));

        if (! Schema::connection(config('session.connection'))->hasTable($table)) {
            throw new \RuntimeException('The configured session table is unavailable; account deactivation was not performed.');
        }

        $deletedSessions = $connection->table($table)->where('user_id', $user->id)->delete();
        $user->setRememberToken(Str::random(60));
        $user->save();

        return $deletedSessions;
    }

    private function sendSecurityEmail(User $user, User $actor, string $action): bool
    {
        try {
            Mail::to($user->email)->send(new AdminAccountSecurityMail(
                name: $user->name,
                action: $action,
                time: now()->timezone(config('app.timezone'))->format('Y-m-d H:i T'),
            ));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Administrator-triggered account security email could not be delivered.', [
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'action' => $action,
                'exception' => $exception,
            ]);
            $this->recordActivity($user, $actor, 'account_security_email', 'failed');

            return false;
        }

        return true;
    }

    private function recordActivity(
        User $user,
        User $actor,
        string $action,
        string $outcome,
        ?string $reason = null,
        ?string $note = null,
    ): void {
        AdminUserActivity::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'outcome' => $outcome,
            'reason' => $reason,
            'note' => $note,
        ]);
    }
}
