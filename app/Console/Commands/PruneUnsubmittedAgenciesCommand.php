<?php

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Services\AdminAgencyManagementService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('agencies:prune-unsubmitted')]
#[Description('Delete agencies that have not submitted documents within twelve hours of registration.')]
class PruneUnsubmittedAgenciesCommand extends Command
{
    public function handle(AdminAgencyManagementService $agencyManagement): int
    {
        $cutoff = now()->subHours(12);
        $deletedCount = 0;

        Agency::query()
            ->select(['id', 'created_at', 'verification_status'])
            ->where('verification_status', '!=', VerificationStatus::VERIFIED->value)
            ->whereDoesntHave('documents')
            ->where('created_at', '<=', $cutoff)
            ->chunkById(100, function (Collection $agencies) use ($agencyManagement, $cutoff, &$deletedCount): void {
                foreach ($agencies as $agency) {
                    if ($agencyManagement->deleteExpiredUnsubmittedAgency($agency, $cutoff)) {
                        $deletedCount++;
                    }
                }
            });

        $this->info(__('messages.abandoned_agencies_pruned', ['count' => $deletedCount]));

        return self::SUCCESS;
    }
}
