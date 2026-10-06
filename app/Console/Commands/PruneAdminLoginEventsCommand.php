<?php

namespace App\Console\Commands;

use App\Models\AdminLoginEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('admin:prune-login-events')]
#[Description('Delete admin login events older than the configured retention period.')]
class PruneAdminLoginEventsCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('admin.login_event_retention_days', 180));
        $deleted = AdminLoginEvent::query()->where('created_at', '<', $cutoff)->delete();

        $this->info(__('messages.admin_login_events_pruned', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
