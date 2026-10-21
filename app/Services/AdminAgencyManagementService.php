<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Mail\AgencyModerationMail;
use App\Models\Agency;
use App\Models\AgencyModerationEvent;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Package;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AgencyActivityNotification;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AdminAgencyManagementService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{agencies: LengthAwarePaginator<int, Agency>, countries: Collection<int, Country>, filters: array<string, mixed>}
     */
    public function listing(string $page, array $filters): array
    {
        $query = Agency::query()
            ->with(['owner:id,name,email', 'countries:id,name'])
            ->withCount(['documents', 'packages', 'reviews', 'bookings'])
            ->withMax('documents', 'uploaded_at');

        match ($page) {
            'pending_verification' => $query->where('verification_status', VerificationStatus::PENDING),
            'verified_agencies' => $query->where('verification_status', VerificationStatus::VERIFIED),
            'unverified_agencies' => $query->whereIn('verification_status', [
                VerificationStatus::PENDING,
                VerificationStatus::REJECTED,
            ]),
            'suspended_agencies' => $query->where('operating_status', 'suspended'),
            default => null,
        };

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('company_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('license_number', 'like', $search)
                    ->orWhere('address', 'like', $search)
                    ->orWhereHas('owner', fn (Builder $ownerQuery) => $ownerQuery
                        ->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search))
                    ->orWhereHas('countries', fn (Builder $countryQuery) => $countryQuery
                        ->where('countries.name', 'like', $search));
            });
        }

        if (($filters['verification_status'] ?? 'all') !== 'all') {
            $query->where('verification_status', $filters['verification_status']);
        }

        if (($filters['operating_status'] ?? 'all') !== 'all') {
            $query->where('operating_status', $filters['operating_status']);
        }

        if (isset($filters['country_id'])) {
            $query->whereHas('countries', fn (Builder $countryQuery) => $countryQuery
                ->whereKey($filters['country_id']));
        }

        if (isset($filters['created_from'])) {
            $query->whereDate('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->whereDate('created_at', '<=', $filters['created_to']);
        }

        return [
            'agencies' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ];
    }

    /**
     * @return array{
     *     agency: Agency,
     *     packages: LengthAwarePaginator<int, Package>,
     *     reviews: LengthAwarePaginator<int, Review>,
     *     bookings: LengthAwarePaginator<int, Booking>,
     *     history: LengthAwarePaginator<int, AgencyModerationEvent>,
     *     missingInformation: array<int, string>,
     *     caseStatus: string,
     *     publishedRating: float,
     *     publishedReviewCount: int
     * }
     */
    public function profile(Agency $agency, Request $request): array
    {
        $agency->load([
            'owner:id,name,email,phone',
            'countries:id,name',
            'services:id,name',
            'documents' => fn ($query) => $query->latest('uploaded_at')->latest('id'),
        ])->loadCount([
            'packages',
            'reviews',
            'bookings',
            'reviews as published_reviews_count' => fn (Builder $query) => $query->where('status', 'published'),
        ])->loadAvg([
            'reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->where('status', 'published'),
        ], 'rating');

        $missingInformation = [];

        foreach ([
            'description' => $agency->description,
            'license_number' => $agency->license_number,
            'address' => $agency->address,
            'phone' => $agency->phone,
            'email' => $agency->email,
            'countries' => $agency->countries->isNotEmpty() ? 'present' : null,
            'services' => $agency->services->isNotEmpty() ? 'present' : null,
            'documents' => $agency->documents->isNotEmpty() ? 'present' : null,
            'owner' => $agency->owner ? 'present' : null,
        ] as $field => $value) {
            if (blank($value)) {
                $missingInformation[] = $field;
            }
        }

        return [
            'agency' => $agency,
            'packages' => $agency->packages()->withCount('bookings')->latest()->paginate(10, ['*'], 'packages_page')->withQueryString(),
            'reviews' => $agency->reviews()->with('user:id,name')->latest()->paginate(10, ['*'], 'reviews_page')->withQueryString(),
            'bookings' => $agency->bookings()->with(['traveler:id,name,email', 'package:id,title'])->latest()->paginate(10, ['*'], 'bookings_page')->withQueryString(),
            'history' => $agency->moderationEvents()->with('actor:id,name')->latest()->paginate(10, ['*'], 'history_page')->withQueryString(),
            'missingInformation' => $missingInformation,
            'caseStatus' => $this->caseStatus($agency),
            'publishedRating' => (float) ($agency->published_reviews_avg_rating ?? 0),
            'publishedReviewCount' => (int) $agency->published_reviews_count,
        ];
    }

    public function decideVerification(
        Agency $agency,
        User $admin,
        string $decision,
        ?string $reason,
    ): bool {
        DB::transaction(function () use ($agency, $admin, $decision, $reason): void {
            $agency = Agency::query()->whereKey($agency->id)->lockForUpdate()->firstOrFail();

            if ($agency->verification_status !== VerificationStatus::PENDING) {
                throw ValidationException::withMessages([
                    'decision' => __('messages.admin_agencies.verification_already_decided'),
                ]);
            }

            if ($decision === 'approve') {
                $hasReviewableDocuments = $agency->documents()
                    ->where('status', '!=', DocumentStatus::REJECTED->value)
                    ->exists();

                if (! $hasReviewableDocuments) {
                    throw ValidationException::withMessages([
                        'decision' => __('messages.admin_agencies.approval_requires_documents'),
                    ]);
                }

                $agency->documents()
                    ->where('status', DocumentStatus::PENDING->value)
                    ->update(['status' => DocumentStatus::APPROVED->value]);
                $newVerificationStatus = VerificationStatus::VERIFIED;
                $action = 'verification_approved';
                $verifiedAt = now();
            } else {
                $agency->documents()
                    ->where('status', DocumentStatus::PENDING->value)
                    ->update(['status' => DocumentStatus::REJECTED->value]);
                $newVerificationStatus = VerificationStatus::REJECTED;
                $action = 'verification_rejected';
                $verifiedAt = null;
            }

            $previousVerificationStatus = $agency->verification_status->value;
            $agency->forceFill([
                'verification_status' => $newVerificationStatus,
                'verified_at' => $verifiedAt,
            ])->save();

            $agency->moderationEvents()->create([
                'actor_id' => $admin->id,
                'action' => $action,
                'previous_verification_status' => $previousVerificationStatus,
                'new_verification_status' => $newVerificationStatus->value,
                'previous_operating_status' => $agency->operating_status,
                'new_operating_status' => $agency->operating_status,
                'reason' => $reason,
            ]);
        });

        $approved = $decision === 'approve';
        $message = $approved
            ? __('messages.agency_verification_approved_notification')
            : __('messages.agency_verification_rejected_notification', ['reason' => $reason]);
        $owner = $agency->owner()->first();

        if (! $owner) {
            return false;
        }

        $owner->notify(new AgencyActivityNotification(
            __('messages.agency_verification_notification_title'),
            $message,
            $approved ? 'verification_approved' : 'verification_rejected',
            route('agency.profile', ['lang' => app()->getLocale()]),
        ));

        return $this->sendModerationEmail(
            $owner,
            $agency,
            $approved ? 'verification_approved' : 'verification_rejected',
            $message,
        );
    }

    public function suspend(Agency $agency, User $admin, ?string $reason = null): bool
    {
        DB::transaction(function () use ($agency, $admin, $reason): void {
            $agency = Agency::query()->whereKey($agency->id)->lockForUpdate()->firstOrFail();

            if ($agency->operating_status === 'suspended') {
                throw ValidationException::withMessages([
                    'suspension' => __('messages.admin_agencies.already_suspended'),
                ]);
            }

            $now = now();
            $agency->forceFill([
                'operating_status' => 'suspended',
                'suspended_at' => $now,
                'suspension_reason' => $reason,
            ])->save();
            $agency->moderationEvents()->create([
                'actor_id' => $admin->id,
                'action' => 'suspended',
                'previous_verification_status' => $agency->verification_status->value,
                'new_verification_status' => $agency->verification_status->value,
                'previous_operating_status' => 'active',
                'new_operating_status' => 'suspended',
                'reason' => $reason,
            ]);
        });

        $agencyName = $agency->company_name;
        $message = __('messages.agency_suspended_notification', ['agency' => $agencyName]);
        $owner = $agency->owner()->first();

        if (! $owner) {
            return false;
        }

        $owner->notify(new AgencyActivityNotification(
            __('messages.agency_moderation_notification_title'),
            $message,
            'agency_suspended',
            route('agency.profile', ['lang' => app()->getLocale()]),
        ));

        return $this->sendModerationEmail(
            $owner,
            $agency,
            'suspended',
            __('messages.agency_suspended_email_body', ['agency' => $agencyName]),
            __('messages.agency_suspended_email_heading'),
        );
    }

    public function reinstate(Agency $agency, User $admin, string $reason): void
    {
        DB::transaction(function () use ($agency, $admin, $reason): void {
            $agency = Agency::query()->whereKey($agency->id)->lockForUpdate()->firstOrFail();

            if ($agency->operating_status !== 'suspended') {
                throw ValidationException::withMessages([
                    'reinstatement' => __('messages.admin_agencies.not_suspended'),
                ]);
            }

            $previousOperatingStatus = $agency->operating_status;
            $agency->forceFill([
                'operating_status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();
            $agency->moderationEvents()->create([
                'actor_id' => $admin->id,
                'action' => 'reinstated',
                'previous_verification_status' => $agency->verification_status->value,
                'new_verification_status' => $agency->verification_status->value,
                'previous_operating_status' => $previousOperatingStatus,
                'new_operating_status' => 'active',
                'reason' => $reason,
            ]);
        });

        $agency->owner()->first()?->notify(new AgencyActivityNotification(
            __('messages.agency_moderation_notification_title'),
            __('messages.agency_reinstated_notification', [
                'agency' => $agency->company_name,
                'reason' => $reason,
            ]),
            'agency_reinstated',
            route('agency.profile', ['lang' => app()->getLocale()]),
        ));
    }

    public function permanentlyDelete(Agency $agency): bool
    {
        $agencyName = $agency->company_name;
        $owner = $agency->owner()->first();

        if (! $this->deleteAgency($agency, false)) {
            return false;
        }

        if (! $owner) {
            return false;
        }

        $message = __('messages.agency_permanently_deleted_notification', [
            'agency' => $agencyName,
        ]);
        $owner->notify(new AgencyActivityNotification(
            __('messages.agency_moderation_notification_title'),
            $message,
            'agency_permanently_deleted',
            route('account.activity', ['lang' => app()->getLocale()]),
        ));

        return $this->sendModerationEmail($owner, $agency, 'permanently_deleted', $message);
    }

    private function sendModerationEmail(
        User $owner,
        Agency $agency,
        string $action,
        string $message,
        ?string $heading = null,
    ): bool {
        try {
            Mail::to($owner)->send(new AgencyModerationMail($agency->company_name, $message, $heading));
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Agency moderation email could not be sent.', [
                'agency_id' => $agency->id,
                'owner_id' => $owner->id,
                'action' => $action,
                'exception' => $exception,
            ]);

            return false;
        }

        return true;
    }

    public function deleteExpiredUnsubmittedAgency(Agency $agency, DateTimeInterface $cutoff): bool
    {
        return $this->deleteAgency($agency, true, $cutoff);
    }

    private function deleteAgency(
        Agency $agency,
        bool $deleteOwnerAccount,
        ?DateTimeInterface $cutoff = null,
    ): bool {
        $storagePaths = DB::transaction(function () use ($agency, $deleteOwnerAccount, $cutoff): ?array {
            $agency = Agency::query()->whereKey($agency->id)->lockForUpdate()->firstOrFail();

            if ($cutoff !== null && (
                $agency->created_at->gt($cutoff)
                || $agency->verification_status === VerificationStatus::VERIFIED
                || $agency->documents()->exists()
            )) {
                return null;
            }

            $owner = User::withTrashed()
                ->whereKey($agency->user_id)
                ->lockForUpdate()
                ->first();
            $packageIds = Package::withTrashed()
                ->where('agency_id', $agency->id)
                ->pluck('id');
            $bookingIds = $agency->bookings()->pluck('id');
            $storagePaths = collect([
                $agency->logo_path,
                $agency->cover_image_path,
                ...$agency->documents()->pluck('file_path')->all(),
                ...$agency->experiences()->pluck('image_path')->all(),
                ...DB::table('package_images')->whereIn('package_id', $packageIds)->pluck('path')->all(),
            ])->filter()->unique()->values()->all();

            DB::table('booking_payment_transactions')->whereIn('booking_id', $bookingIds)->delete();
            $agency->reviews()->delete();
            $agency->conversations()->delete();
            $agency->inquiries()->delete();
            $agency->bookings()->delete();
            $agency->trustScoreLogs()->delete();
            $agency->forceDelete();

            if ($deleteOwnerAccount) {
                if ($owner && ! $owner->trashed()) {
                    $owner->delete();
                }
            } else {
                $owner?->forceFill(['role' => UserRole::TRAVELER])->save();
            }

            return $storagePaths;
        });

        if ($storagePaths === null) {
            return false;
        }

        $disk = Storage::disk('public');
        $existingPaths = array_values(array_filter(
            $storagePaths,
            fn (string $path): bool => $disk->exists($path),
        ));

        if ($existingPaths !== [] && ! $disk->delete($existingPaths)) {
            throw new \RuntimeException('Agency records were deleted, but some agency media could not be removed.');
        }

        return true;
    }

    private function caseStatus(Agency $agency): string
    {
        if ($agency->operating_status === 'suspended') {
            return 'suspended';
        }

        return match ($agency->verification_status) {
            VerificationStatus::VERIFIED => 'verified',
            VerificationStatus::REJECTED => 'rejected',
            VerificationStatus::PENDING => $agency->documents->isEmpty() ? 'never_submitted' : 'pending',
        };
    }
}
