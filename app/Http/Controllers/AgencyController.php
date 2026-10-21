<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\PackageStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\AgencyExperience;
use App\Models\Country;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\PackageImage;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AdminAgencyDocumentReceived;
use App\Notifications\AgencyActivityNotification;
use App\Services\BookingService;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AgencyController extends Controller
{
    private function currentAgency(): Agency
    {
        $user = auth()->user();

        abort_unless($user && $user->isAgencyOwner(), 403);

        return $user->agency()->firstOrFail();
    }

    private function notifyAdminsOfDocument(AgencyDocument $document): void
    {
        $admins = User::query()->where('role', UserRole::ADMIN)->get();

        Notification::send($admins, new AdminAgencyDocumentReceived($document));
    }

    private function ensurePackageOwnership(Package $package): void
    {
        abort_unless($package->agency_id === $this->currentAgency()->id, 403);
    }

    public function dashboard(BookingService $bookingService): View
    {
        $agency = $this->currentAgency();
        $departureItems = $agency->packages()
            ->with(['availabilities' => fn ($query) => $query->where('status', 'active')->whereDate('departure_date', '>', today()->toDateString())->orderBy('departure_date')->limit(5)])
            ->get()
            ->flatMap(fn ($package) => $package->availabilities->map(fn ($availability) => [
                'package' => $package,
                'availability' => $availability,
            ]))->sortBy('availability.departure_date')->take(5)->values();
        $capacitySnapshots = $bookingService->capacitySnapshots($departureItems->pluck('availability'));
        $upcomingDepartures = $departureItems->map(function (array $item) use ($capacitySnapshots): array {
            $snapshot = $capacitySnapshots->get($item['availability']->id);

            return $item + [
                'remaining_capacity' => $snapshot['remaining_capacity'],
                'capacity_snapshot' => $snapshot,
            ];
        });
        $recentPackages = $agency->packages()
            ->withCount(['images', 'bookings', 'availabilities as upcoming_departures_count' => fn ($query) => $query->where('status', 'active')->whereDate('departure_date', '>', today()->toDateString())])
            ->withSum('bookings as travelers_booked', 'traveler_count')
            ->latest()
            ->take(5)
            ->get();
        $incompletePackageCount = $agency->packages()
            ->whereDoesntHave('images')
            ->count();
        $bookingStatusCounts = $agency->bookings()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $recentInquiries = $agency->inquiries()->with('user')->latest()->take(5)->get();

        return view('agencies.dashboard', [
            'agency' => $agency,
            'packageCount' => $agency->packages()->count(),
            'publishedPackageCount' => $agency->packages()->where('status', PackageStatus::PUBLISHED->value)->count(),
            'draftPackageCount' => $agency->packages()->where('status', PackageStatus::DRAFT->value)->count(),
            'upcomingDepartures' => $upcomingDepartures,
            'pendingBookingCount' => $agency->bookings()->where('status', 'pending')->count(),
            'confirmedBookingCount' => $agency->bookings()->where('status', 'confirmed')->count(),
            'bookingStatusCounts' => $bookingStatusCounts,
            'incompletePackageCount' => $incompletePackageCount,
            'openInquiryCount' => $agency->inquiries()->where('status', 'open')->count(),
            'recentBookings' => $agency->bookings()->with(['package', 'availability', 'traveler'])->latest()->limit(5)->get(),
            'recentPackages' => $recentPackages,
            'recentInquiries' => $recentInquiries,
            'notificationCount' => $agency->owner->unreadNotifications()->count(),
        ]);
    }

    public function profile(): View
    {
        $agency = $this->currentAgency();
        $agency->load(['owner', 'countries', 'services', 'documents']);

        return view('agencies.profile', [
            'agency' => $agency,
            'countries' => Country::orderBy('name')->get(),
            'services' => Service::orderBy('name')->get(),
            'latestVerificationDocument' => $agency->documents->sortByDesc('uploaded_at')->first(),
        ]);
    }

    public function replyToInquiry(Request $request, Inquiry $inquiry, ConversationService $conversationService): RedirectResponse
    {
        $agency = $this->currentAgency();
        abort_unless($inquiry->agency_id === $agency->id, 403);

        $data = $request->validate([
            'reply_message' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $updateData = [
            'status' => 'responded',
        ];

        if (Schema::hasColumn('inquiries', 'reply_message')) {
            $updateData['reply_message'] = $data['reply_message'];
        }

        if (Schema::hasColumn('inquiries', 'replied_at')) {
            $updateData['replied_at'] = now();
        }

        $inquiry->update($updateData);

        $conversation = $conversationService->startForInquiry($inquiry->load('user'));
        $conversationService->send($conversation, $agency->owner, $data['reply_message']);
        $conversationPath = parse_url(route('agency.conversations.show', [
            'conversation' => $conversation->id,
            'lang' => app()->getLocale(),
        ]), PHP_URL_PATH);

        $agency->owner
            ->unreadNotifications()
            ->where('type', AgencyActivityNotification::class)
            ->get()
            ->filter(function ($notification) use ($inquiry, $conversationPath): bool {
                $data = $notification->data ?? [];
                $message = strtolower((string) ($data['message'] ?? ''));
                $title = strtolower((string) ($data['title'] ?? ''));
                $subject = strtolower($inquiry->subject);

                $isInquiryNotification = ($data['type'] ?? null) === 'inquiry'
                    && ($message === '' || str_contains($message, $subject) || str_contains($title, $subject));
                $isConversationNotification = ($data['type'] ?? null) === 'conversation'
                    && parse_url((string) ($data['url'] ?? ''), PHP_URL_PATH) === $conversationPath;

                return $isInquiryNotification || $isConversationNotification;
            })
            ->each(fn ($notification) => $notification->markAsRead());

        return back()->with('inquiry_reply_status', 'Your reply was sent to the traveler.');
    }

    public function closeInquiry(Inquiry $inquiry): RedirectResponse
    {
        $agency = $this->currentAgency();
        abort_unless($inquiry->agency_id === $agency->id, 403);

        $updateData = ['status' => 'closed'];

        if (Schema::hasColumn('inquiries', 'replied_at')) {
            $updateData['replied_at'] = $inquiry->replied_at ?? now();
        }

        $inquiry->update($updateData);
        $inquiry->conversation?->update(['status' => 'closed']);

        $inquiry->user?->notify(new AgencyActivityNotification(
            'Inquiry closed',
            'This inquiry has been closed by the agency: '.$inquiry->subject.'.',
            'inquiry_closed',
            route('account.activity', ['lang' => app()->getLocale()])
        ));

        return back()->with('inquiry_close_status', 'This inquiry was marked as closed and will disappear from the list after the next refresh.');
    }

    public function destroyInquiry(Inquiry $inquiry): RedirectResponse
    {
        $agency = $this->currentAgency();
        abort_unless($inquiry->agency_id === $agency->id, 403);
        abort_unless($inquiry->status instanceof InquiryStatus ? $inquiry->status->value === 'closed' : $inquiry->status === 'closed', 403);

        $inquiry->delete();

        return redirect()->route('agency.inquiries.index', ['lang' => app()->getLocale()])
            ->with('inquiry_delete_status', 'The closed inquiry was deleted.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,'.$agency->owner->id],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+237\s?(?:\d[\s-]?){8}\d$/'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'agency_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+237\s?(?:\d[\s-]?){8}\d$/'],
            'agency_email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'url', 'max:255'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['exists:countries,id'],
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:services,id'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_cover_image' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => __('messages.cameroon_phone_invalid'),
            'agency_phone.regex' => __('messages.cameroon_phone_invalid'),
        ]);

        $normalizeCameroonPhone = static function (?string $value): ?string {
            if ($value === null || trim($value) === '') {
                return null;
            }

            $digits = preg_replace('/\D+/', '', $value);
            if (! preg_match('/^237\d{9}$/', $digits)) {
                return $value;
            }

            return '+237 '.trim(chunk_split(substr($digits, 3), 3, ' '));
        };

        $data['phone'] = $normalizeCameroonPhone($data['phone'] ?? null);
        $data['agency_phone'] = $normalizeCameroonPhone($data['agency_phone'] ?? null);

        $disk = Storage::disk('public');
        $oldPaths = [];
        $newPaths = [];
        $agencyUpdates = [
            'company_name' => $data['company_name'],
            'description' => $data['description'] ?? '',
            'license_number' => $data['license_number'] ?? '',
            'address' => $data['address'] ?? null,
            'phone' => $data['agency_phone'] ?? $agency->phone,
            'email' => $data['agency_email'] ?? $agency->email,
            'website' => $data['website'] ?? $agency->website,
        ];

        foreach ([
            'logo' => ['directory' => 'agency-logos', 'column' => 'logo_path', 'remove' => 'remove_logo'],
            'cover_image' => ['directory' => 'agency-covers', 'column' => 'cover_image_path', 'remove' => 'remove_cover_image'],
        ] as $input => $options) {
            if ($request->hasFile($input)) {
                $path = $request->file($input)->storePublicly($options['directory'].'/'.$agency->id, 'public');
                if (! $path) {
                    $disk->delete($newPaths);

                    return back()->withErrors([$input => __('messages.upload_failed')])->withInput();
                }
                $newPaths[] = $path;
                $agencyUpdates[$options['column']] = $path;
                if ($agency->{$options['column']}) {
                    $oldPaths[] = $agency->{$options['column']};
                }
            } elseif ($request->boolean($options['remove'])) {
                if ($agency->{$options['column']}) {
                    $oldPaths[] = $agency->{$options['column']};
                }
                $agencyUpdates[$options['column']] = null;
            }
        }

        try {
            $owner = $agency->owner;
            DB::transaction(function () use ($owner, $agency, $data, $agencyUpdates): void {
                $owner->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? $owner->phone,
                ]);
                $agency->update($agencyUpdates);
                $agency->countries()->sync($data['countries'] ?? []);
                $agency->services()->sync($data['services'] ?? []);
            });
        } catch (\Throwable $exception) {
            $disk->delete($newPaths);
            throw $exception;
        }

        $currentPaths = array_filter([$agency->logo_path, $agency->cover_image_path]);
        $disk->delete(array_diff($oldPaths, $currentPaths));

        return redirect()->route('agency.profile', ['lang' => app()->getLocale()])
            ->with('status', __('messages.agency_profile_updated'));
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(DocumentType::cases(), 'value'))],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $latestDocument = $agency->documents()->latest('uploaded_at')->first();

        if ($latestDocument && $latestDocument->canBeModified()) {
            return back()->withErrors(['document' => __('messages.document_update_locked')])->withInput();
        }

        $filePath = $request->file('document')->store('agency-documents', 'public');
        if (! $filePath) {
            return back()->withErrors(['document' => __('messages.upload_failed')])->withInput();
        }

        $document = DB::transaction(function () use ($agency, $request, $data, $filePath): AgencyDocument {
            $lockedAgency = Agency::query()->whereKey($agency->id)->lockForUpdate()->firstOrFail();
            $document = $lockedAgency->documents()->create([
                'type' => $data['type'],
                'file_path' => $filePath,
                'status' => DocumentStatus::PENDING->value,
                'uploaded_at' => now(),
            ]);
            $document->setRelation('agency', $lockedAgency);

            if ($lockedAgency->verification_status === VerificationStatus::REJECTED) {
                $lockedAgency->forceFill([
                    'verification_status' => VerificationStatus::PENDING,
                    'verified_at' => null,
                ])->save();
                $lockedAgency->moderationEvents()->create([
                    'actor_id' => $request->user()->id,
                    'action' => 'verification_resubmitted',
                    'previous_verification_status' => VerificationStatus::REJECTED->value,
                    'new_verification_status' => VerificationStatus::PENDING->value,
                    'previous_operating_status' => $lockedAgency->operating_status,
                    'new_operating_status' => $lockedAgency->operating_status,
                ]);
            }

            return $document;
        });

        $this->notifyAdminsOfDocument($document);

        return redirect()->route('agency.profile', ['lang' => app()->getLocale()])->with('status', __('messages.document_uploaded'));
    }

    public function updateDocument(Request $request, AgencyDocument $document): RedirectResponse
    {
        $agency = $this->currentAgency();

        abort_unless($document->agency_id === $agency->id, 403);

        if (! $document->canBeModified()) {
            return back()->withErrors(['document' => __('messages.document_update_locked')]);
        }

        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $disk = Storage::disk('public');

        if ($document->file_path && $disk->exists($document->file_path)) {
            $disk->delete($document->file_path);
        }

        $document->update([
            'file_path' => $request->file('document')->store('agency-documents', 'public'),
            'status' => DocumentStatus::PENDING->value,
            'uploaded_at' => now(),
        ]);
        $document->load('agency:id,company_name');
        $this->notifyAdminsOfDocument($document);

        return redirect()->route('agency.profile', ['lang' => app()->getLocale()])->with('status', __('messages.document_updated'));
    }

    public function destroyDocument(AgencyDocument $document): RedirectResponse
    {
        $agency = $this->currentAgency();

        abort_unless($document->agency_id === $agency->id, 403);

        if (! $document->canBeModified()) {
            return back()->withErrors(['document' => __('messages.document_update_locked')]);
        }

        $disk = Storage::disk('public');

        if ($document->file_path && $disk->exists($document->file_path)) {
            $disk->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('agency.profile', ['lang' => app()->getLocale()])->with('status', __('messages.document_deleted'));
    }

    public function storeExperience(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'destination' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $agency->experiences()->create([
            'image_path' => $request->file('image')->store('agency-experiences', 'public'),
            'destination' => $data['destination'],
            'caption' => $data['caption'] ?? null,
            'alt_text' => $data['alt_text'] ?? $data['destination'],
            'published_at' => now(),
        ]);

        return redirect()->route('agency.dashboard')->with('status', __('messages.experience_published'));
    }

    public function destroyExperience(AgencyExperience $experience): RedirectResponse
    {
        $agency = $this->currentAgency();
        abort_unless($experience->agency_id === $agency->id, 403);

        $disk = Storage::disk('public');
        if ($disk->exists($experience->image_path)) {
            $disk->delete($experience->image_path);
        }

        $experience->delete();

        return redirect()->route('agency.show', [
            'slug' => Str::slug($agency->company_name),
            'lang' => app()->getLocale(),
        ])->with('experience_deleted', true);
    }

    public function createPackage(): View
    {
        $agency = $this->currentAgency();

        return view('agencies.packages.form', [
            'agency' => $agency,
            'destinations' => $agency->countries()->orderBy('countries.name')->pluck('countries.name'),
            'package' => null,
            'canCreatePackage' => $agency->verification_status === VerificationStatus::VERIFIED
                && $agency->operating_status === 'active',
        ]);
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        if ($agency->operating_status === 'suspended') {
            return back()
                ->withErrors(['verification' => __('messages.agency_suspended_cannot_create_packages')])
                ->withInput();
        }

        if ($agency->verification_status !== VerificationStatus::VERIFIED) {
            return back()
                ->withErrors(['verification' => __('messages.agency_must_be_verified_to_create_packages')])
                ->withInput();
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'destination' => ['required', 'string', Rule::in($agency->countries()->pluck('countries.name')->all())],
            'price_from' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'status' => ['required', 'in:'.implode(',', array_map(fn ($status) => $status->value, PackageStatus::cases()))],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $slugBase = Str::slug($data['title']) ?: 'package';
        $slug = $slugBase;
        $suffix = 2;

        while ($agency->packages()->where('slug', $slug)->exists()) {
            $slug = $slugBase.'-'.$suffix;
            $suffix++;
        }

        $package = $agency->packages()->create([
            'slug' => $slug,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'destination' => $data['destination'],
            'price_from' => $data['price_from'],
            'duration_days' => $data['duration_days'],
            'status' => $data['status'],
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => $data['status'] === PackageStatus::PUBLISHED->value ? now() : null,
        ]);

        return redirect()->route('agency.packages.edit', ['package' => $package->id])->with('status', __('messages.package_created', ['title' => $package->title]));
    }

    public function editPackage(Package $package): View
    {
        $this->authorize('update', $package);

        $agency = $this->currentAgency();

        return view('agencies.packages.form', [
            'agency' => $agency,
            'destinations' => $agency->countries()->orderBy('countries.name')->pluck('countries.name'),
            'package' => $package->load(['images'])
                ->loadCount(['bookings', 'availabilities as upcoming_departures_count' => fn ($query) => $query->where('status', 'active')->whereDate('departure_date', '>', today()->toDateString())])
                ->loadSum('bookings as travelers_booked', 'traveler_count'),
            'canCreatePackage' => $agency->verification_status === VerificationStatus::VERIFIED
                && $agency->operating_status === 'active',
        ]);
    }

    public function updatePackage(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        if ($package->agency->operating_status === 'suspended'
            && $request->input('status') === PackageStatus::PUBLISHED->value) {
            return back()
                ->withErrors(['verification' => __('messages.agency_suspended_cannot_create_packages')])
                ->withInput();
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'destination' => ['required', 'string', Rule::in($package->agency->countries()->pluck('countries.name')->all())],
            'price_from' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'status' => ['required', 'in:'.implode(',', array_map(fn ($status) => $status->value, PackageStatus::cases()))],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        $slugBase = Str::slug($data['title']) ?: 'package';
        $slug = $slugBase;
        $suffix = 2;

        while ($package->agency->packages()->where('slug', $slug)->whereKeyNot($package->id)->exists()) {
            $slug = $slugBase.'-'.$suffix;
            $suffix++;
        }

        $package->update([
            'slug' => $slug,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'destination' => $data['destination'],
            'price_from' => $data['price_from'],
            'duration_days' => $data['duration_days'],
            'status' => $data['status'],
            'is_featured' => $request->boolean('is_featured'),
            'published_at' => $data['status'] === PackageStatus::PUBLISHED->value ? ($package->published_at ?? now()) : null,
        ]);

        return redirect()->route('agency.packages.edit', ['package' => $package->id])->with('status', __('messages.package_updated', ['title' => $package->title]));
    }

    public function destroyPackage(Package $package): RedirectResponse
    {
        $this->authorize('delete', $package);
        $this->ensurePackageOwnership($package);
        $package->delete();

        return redirect()->route('agency.packages.index')->with('status', __('messages.package_deleted', ['title' => $package->title]));
    }

    public function duplicatePackage(Package $package): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        $agency = $package->agency;

        if ($agency->operating_status === 'suspended') {
            return back()
                ->withErrors(['verification' => __('messages.agency_suspended_cannot_create_packages')]);
        }

        if ($agency->verification_status !== VerificationStatus::VERIFIED) {
            return back()
                ->withErrors(['verification' => __('messages.agency_must_be_verified_to_create_packages')]);
        }

        $package->load(['images']);
        $copiedImagePaths = [];

        try {
            $duplicate = DB::transaction(function () use ($package, &$copiedImagePaths): Package {
                $agency = $package->agency;
                $title = __('messages.duplicate_package_title', ['title' => $package->title]);
                $slugBase = Str::slug($title) ?: 'package-copy';
                $slug = $slugBase;
                $suffix = 2;

                while ($agency->packages()->where('slug', $slug)->exists()) {
                    $slug = $slugBase.'-'.$suffix;
                    $suffix++;
                }

                $duplicate = $agency->packages()->create([
                    'slug' => $slug,
                    'title' => $title,
                    'summary' => $package->summary,
                    'description' => $package->description,
                    'destination' => $package->destination,
                    'price_from' => $package->price_from,
                    'duration_days' => $package->duration_days,
                    'status' => PackageStatus::DRAFT,
                    'published_at' => null,
                    'is_featured' => false,
                ]);

                foreach ($package->images as $image) {
                    $extension = pathinfo($image->path, PATHINFO_EXTENSION);
                    $copyPath = 'package-images/'.$agency->id.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');

                    if (! Storage::disk('public')->copy($image->path, $copyPath)) {
                        throw new \RuntimeException(__('messages.package_image_duplicate_failed'));
                    }

                    $copiedImagePaths[] = $copyPath;
                    $duplicate->images()->create([
                        'path' => $copyPath,
                        'caption' => $image->caption,
                        'is_primary' => $image->is_primary,
                        'sort_order' => $image->sort_order,
                    ]);
                }

                return $duplicate;
            });
        } catch (\RuntimeException $exception) {
            Storage::disk('public')->delete($copiedImagePaths);

            return back()->withErrors(['duplicate' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($copiedImagePaths);
            throw $exception;
        }

        return redirect()->route('agency.packages.edit', ['package' => $duplicate->id])
            ->with('status', __('messages.package_duplicated', ['title' => $duplicate->title]));
    }

    public function indexPackages(Request $request): View
    {
        $agency = $this->currentAgency();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));

        $packages = $agency->packages()
            ->when($status && in_array($status, array_map(fn ($item) => $item->value, PackageStatus::cases()), true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%");
                });
            })
            ->with('images')
            ->withCount(['images', 'bookings', 'availabilities as upcoming_departures_count' => fn ($query) => $query->where('status', 'active')->whereDate('departure_date', '>', today()->toDateString())])
            ->withSum('bookings as travelers_booked', 'traveler_count')
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->appends($request->query());

        return view('agencies.packages.index', [
            'agency' => $agency,
            'packages' => $packages,
            'search' => $search,
            'status' => $status,
            'statusCounts' => $agency->packages()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function uploadPackageImage(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('image')->store('package-images', 'public');
        $isPrimary = $request->boolean('is_primary');

        if ($isPrimary) {
            $package->images()->update(['is_primary' => false]);
        }

        $package->images()->create([
            'path' => $path,
            'caption' => $request->input('caption'),
            'is_primary' => $isPrimary,
            'sort_order' => $package->images()->max('sort_order') + 1,
        ]);

        return back()->with('status', __('messages.package_image_uploaded'));
    }

    public function deletePackageImage(Package $package, PackageImage $image): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        abort_unless($image->package_id === $package->id, 404);

        $wasPrimary = $image->is_primary;

        if ($wasPrimary) {
            $replacement = $package->images()->whereKeyNot($image->id)->orderBy('sort_order')->orderBy('id')->first();
            if ($replacement) {
                PackageImage::query()->whereKey($replacement->id)->update(['is_primary' => true]);
            }
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('status', __('messages.package_image_deleted'));
    }

    public function setPrimaryPackageImage(Package $package, PackageImage $image): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        abort_unless($image->package_id === $package->id, 404);

        $package->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('status', __('messages.primary_image_updated'));
    }
}
