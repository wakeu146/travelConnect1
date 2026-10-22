<?php

namespace App\Services;

use App\Enums\PackageAvailabilityStatus;
use App\Enums\PackageStatus;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageModerationEvent;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminTravelManagementService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{packages: LengthAwarePaginator<int, Package>, filters: array<string, mixed>}
     */
    public function packages(string $page, array $filters): array
    {
        $query = Package::query()
            ->with(['agency:id,user_id,company_name', 'agency.owner:id,name', 'images'])
            ->withCount([
                'availabilities as active_availabilities_count' => fn (Builder $availabilityQuery) => $availabilityQuery
                    ->where('status', PackageAvailabilityStatus::ACTIVE->value)
                    ->whereDate('departure_date', '>=', today()),
            ]);

        $presetStatus = match ($page) {
            'published_packages' => PackageStatus::PUBLISHED->value,
            'draft_unpublished_packages' => 'unpublished',
            default => 'all',
        };
        $status = $filters['status'] ?? $presetStatus;

        if ($status === 'unpublished') {
            $query->whereIn('status', [PackageStatus::DRAFT->value, PackageStatus::ARCHIVED->value]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('title', 'like', $search)
                    ->orWhere('destination', 'like', $search)
                    ->orWhereHas('agency', fn (Builder $agencyQuery) => $agencyQuery
                        ->where('company_name', 'like', $search));
            });
        }

        if (($filters['destination'] ?? '') !== '') {
            $query->where('destination', $filters['destination']);
        }

        return [
            'packages' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => ['status' => $status, ...$filters],
        ];
    }

    /**
     * @return array{package: Package, history: LengthAwarePaginator<int, PackageModerationEvent>}
     */
    public function package(Package $package): array
    {
        $package->load([
            'agency.owner:id,name,email',
            'images',
            'availabilities' => fn ($query) => $query->orderBy('departure_date'),
        ]);

        return [
            'package' => $package,
            'history' => $package->moderationEvents()
                ->with('actor:id,name')
                ->latest()
                ->latest('id')
                ->paginate(10, ['*'], 'history_page')
                ->withQueryString(),
        ];
    }

    public function moderatePackage(Package $package, int $actorId, string $decision, string $reason): void
    {
        DB::transaction(function () use ($package, $actorId, $decision, $reason): void {
            $lockedPackage = Package::query()->lockForUpdate()->findOrFail($package->id);
            $previousStatus = $lockedPackage->status->value;
            $newStatus = match ($decision) {
                'restrict' => PackageStatus::ARCHIVED,
                'restore' => PackageStatus::DRAFT,
            };

            if ($previousStatus === $newStatus->value) {
                throw ValidationException::withMessages([
                    'decision' => __('messages.admin_travel.invalid_moderation_transition'),
                ]);
            }

            $lockedPackage->update([
                'status' => $newStatus,
                'published_at' => null,
            ]);
            $lockedPackage->moderationEvents()->create([
                'actor_id' => $actorId,
                'action' => $decision,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus->value,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{countries: LengthAwarePaginator<int, Country>, filters: array<string, mixed>}
     */
    public function countries(array $filters): array
    {
        $query = Country::query()->withCount(['agencies']);

        if (($filters['search'] ?? '') !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('code', 'like', '%'.$filters['search'].'%');
        }

        return [
            'countries' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{services: LengthAwarePaginator<int, Service>, filters: array<string, mixed>}
     */
    public function services(array $filters): array
    {
        $query = Service::query()->withCount(['agencies']);

        if (($filters['search'] ?? '') !== '') {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        return [
            'services' => $query->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
        ];
    }

    /**
     * @return array{destinations: LengthAwarePaginator<int, \stdClass>}
     */
    public function destinations(): array
    {
        $destinations = Package::query()
            ->select('destination')
            ->selectRaw('COUNT(*) as packages_count')
            ->whereNotNull('destination')
            ->where('destination', '!=', '')
            ->groupBy('destination')
            ->orderBy('destination')
            ->paginate(20)
            ->withQueryString();

        return ['destinations' => $destinations];
    }
}
