<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('admin:create')]
#[Description('Create an administrator interactively.')]
class AdminCreateCommand extends Command
{
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error(__('messages.admin_command_interactive_only'));

            return self::FAILURE;
        }

        $name = trim((string) $this->ask(__('messages.admin_name_prompt')));
        $email = Str::lower(trim((string) $this->ask(
            __('messages.admin_email_prompt'),
            'softwareengdev3@gmail.com',
        )));

        if ($name === '' || Str::length($name) > 255 || Str::length($email) > 191
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error(__('messages.admin_command_invalid_identity'));

            return self::FAILURE;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error(__('messages.admin_email_already_exists'));

            return self::FAILURE;
        }

        $password = (string) $this->secret(__('messages.admin_password_prompt'));
        $passwordConfirmation = (string) $this->secret(__('messages.admin_password_confirm_prompt'));

        if (strlen($password) < 12 || ! hash_equals($password, $passwordConfirmation)) {
            $this->error(__('messages.admin_password_invalid'));

            return self::FAILURE;
        }

        $admin = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $admin->forceFill([
            'role' => UserRole::ADMIN,
            'admin_session_version' => 1,
        ])->save();

        $this->info(__('messages.admin_created', ['email' => $admin->email]));

        return self::SUCCESS;
    }
}
