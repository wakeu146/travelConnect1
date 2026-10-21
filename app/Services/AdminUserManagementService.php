<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\AdminAccountSecurityMail;
use App\Models\AdminUserActivity;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminUserManagementService
{
    /**
     * Permanently removes an account and its related user and agency data.
     *
     * @return bool|null Whether an agency-owner deletion email was sent; null when the account had no agency.
     */
    public function permanentlyDelete(
        User $actor,
        int $userId,
        AdminAgencyManagementService $agencyManagement,
    ): ?bool {
        $this->assertAdministrator($actor);

        $deletedAccount = DB::transaction(function () use ($userId, $agencyManagement): array {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            $agency = Agency::withTrashed()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $agencyMediaPaths = $agency
                ? $agencyManagement->deleteAgencyForAccountRemoval($agency)
                : [];
            $bookingIds = DB::table('bookings')
                ->where('user_id', $user->id)
                ->pluck('id');

            DB::table('booking_payment_transactions')
                ->whereIn('booking_id', $bookingIds)
                ->delete();
            DB::table('bookings')->where('user_id', $user->id)->delete();
            DB::table('conversation_messages')->where('sender_id', $user->id)->delete();
            DB::table('conversations')->where('traveler_id', $user->id)->delete();
            DB::table('inquiries')->where('user_id', $user->id)->delete();
            DB::table('reviews')->where('user_id', $user->id)->delete();

            $user->notifications()->delete();

            if (Schema::hasTable('password_reset_codes')) {
                DB::table('password_reset_codes')
                    ->where('email', Str::lower($user->email))
                    ->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }

            $sessionConnectionName = config('session.connection');
            $sessionTable = (string) config('session.table', 'sessions');
            if (Schema::connection($sessionConnectionName)->hasTable($sessionTable)) {
                DB::connection($sessionConnectionName)
                    ->table($sessionTable)
                    ->where('user_id', $user->id)
                    ->delete();
            }

            $profilePhotoPath = $user->profile_photo_path;
            $role = $user->role->value;
            $user->forceDelete();

            return [
                'user' => $user,
                'agency' => $agency,
                'agency_media_paths' => $agencyMediaPaths,
                'profile_photo_path' => $profilePhotoPath,
                'role' => $role,
            ];
        });

        $this->deleteStoredFiles([
            ...$deletedAccount['agency_media_paths'],
            $deletedAccount['profile_photo_path'],
        ]);

        Log::info('An administrator permanently deleted a user account.', [
            'actor_id' => $actor->id,
            'user_id' => $userId,
            'role' => $deletedAccount['role'],
            'reason' => __('messages.admin_users.automatic_reasons.permanently_deleted', [
                'name' => $deletedAccount['user']->name,
            ]),
        ]);

        if ($deletedAccount['agency'] instanceof Agency) {
            return $agencyManagement->sendPermanentDeletionEmail(
                $deletedAccount['user'],
                $deletedAccount['agency'],
            );
        }

        return null;
    }

    public function deactivate(User $actor, int $userId): bool
    {
        $this->assertAdministrator($actor);

        $user = DB::transaction(function () use ($actor, $userId): User {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            if ($user->trashed()) {
                throw ValidationException::withMessages([
                    'user' => __('messages.admin_users.already_deactivated'),
                ]);
            }

            $this->revokeDatabaseSessions($user);
            $user->delete();

            $this->recordActivity(
                $user,
                $actor,
                'deactivated',
                'success',
                __('messages.admin_users.automatic_reasons.deactivated', ['name' => $user->name]),
            );

            return $user;
        });

        return $this->sendSecurityEmail($user, $actor, 'account_deactivated');
    }

    public function revokeSessions(User $actor, int $userId): bool
    {
        $this->assertAdministrator($actor);

        $user = DB::transaction(function () use ($actor, $userId): User {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            if ($user->trashed()) {
                throw ValidationException::withMessages([
                    'user' => __('messages.admin_users.already_deactivated'),
                ]);
            }

            $this->revokeDatabaseSessions($user);

            $this->recordActivity(
                $user,
                $actor,
                'sessions_revoked',
                'success',
                __('messages.admin_users.automatic_reasons.sessions_revoked', ['name' => $user->name]),
            );

            return $user;
        });

        return $this->sendSecurityEmail($user, $actor, 'sessions_revoked');
    }

    public function restore(User $actor, int $userId): bool
    {
        $this->assertAdministrator($actor);

        $user = DB::transaction(function () use ($actor, $userId): User {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($userId);
            $this->assertManageableAccount($user);

            if (! $user->trashed()) {
                throw ValidationException::withMessages([
                    'user' => __('messages.admin_users.already_active'),
                ]);
            }

            $user->restore();

            $this->recordActivity(
                $user,
                $actor,
                'restored',
                'success',
                __('messages.admin_users.automatic_reasons.restored', ['name' => $user->name]),
            );

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

    /**
     * @param  array<int, string|null>  $paths
     */
    private function deleteStoredFiles(array $paths): void
    {
        $disk = Storage::disk('public');
        $existingPaths = array_values(array_filter(
            array_unique(array_filter($paths)),
            fn (string $path): bool => $disk->exists($path),
        ));

        if ($existingPaths !== [] && ! $disk->delete($existingPaths)) {
            throw new \RuntimeException('The account was deleted, but some related profile or agency media could not be removed.');
        }
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
