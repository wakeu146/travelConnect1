<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PackageStatus;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Models\AgencyExperience;
use App\Models\Country;
use App\Models\Package;
use App\Models\PackageImage;
use App\Models\PackageItineraryDay;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgencyController extends Controller
{
    private function currentAgency(): Agency
    {
        $user = auth()->user();

        abort_unless($user && $user->isAgencyOwner(), 403);

        return $user->agency()->firstOrFail();
    }

    private function ensurePackageOwnership(Package $package): void
    {
        abort_unless($package->agency_id === $this->currentAgency()->id, 403);
    }

    public function dashboard(): View
    {
        $agency = $this->currentAgency();

        return view('agencies.dashboard', [
            'agency' => $agency,
            'packageCount' => $agency->packages()->count(),
            'publishedPackageCount' => $agency->packages()->where('status', PackageStatus::PUBLISHED->value)->count(),
            'draftPackageCount' => $agency->packages()->where('status', PackageStatus::DRAFT->value)->count(),
            'recentPackages' => $agency->packages()->latest()->take(5)->get(),
            'recentInquiries' => $agency->inquiries()->with('user')->latest()->take(5)->get(),
        ]);
    }

    public function profile(): View
    {
        $agency = $this->currentAgency();

        return view('agencies.profile', [
            'agency' => $agency,
            'countries' => Country::orderBy('name')->get(),
            'services' => Service::orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,' . $agency->owner->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'agency_phone' => ['nullable', 'string', 'max:30'],
            'agency_email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'url', 'max:255'],
            'countries' => ['nullable', 'array'],
            'countries.*' => ['exists:countries,id'],
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:services,id'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        $owner = $agency->owner;
        $owner->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? $owner->phone,
        ]);

        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('agency-logos', 'public');
            $agency->update(['logo_path' => $logoPath]);
        }

        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('agency-covers', 'public');
            $agency->update(['cover_image_path' => $coverPath]);
        }

        $agency->update([
            'company_name' => $data['company_name'],
            'description' => $data['description'] ?? '',
            'license_number' => $data['license_number'] ?? '',
            'address' => $data['address'] ?? null,
            'phone' => $data['agency_phone'] ?? $agency->phone,
            'email' => $data['agency_email'] ?? $agency->email,
            'website' => $data['website'] ?? $agency->website,
        ]);

        $agency->countries()->sync($data['countries'] ?? []);
        $agency->services()->sync($data['services'] ?? []);

        return redirect()->route('agency.profile')->with('status', __('messages.agency_profile_updated'));
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(DocumentType::cases(), 'value'))],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $agency->documents()->create([
            'type' => $data['type'],
            'file_path' => $request->file('document')->store('agency-documents', 'public'),
            'status' => DocumentStatus::PENDING->value,
            'uploaded_at' => now(),
        ]);

        return redirect()->route('agency.dashboard')->with('status', __('messages.document_uploaded'));
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

    public function createPackage(): View
    {
        $agency = $this->currentAgency();

        return view('agencies.packages.form', [
            'agency' => $agency,
            'package' => null,
        ]);
    }

    public function storePackage(Request $request): RedirectResponse
    {
        $agency = $this->currentAgency();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'destination' => ['required', 'string', 'max:255'],
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

        return view('agencies.packages.form', [
            'agency' => $this->currentAgency(),
            'package' => $package->load(['images', 'itineraryDays']),
        ]);
    }

    public function updatePackage(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'destination' => ['required', 'string', 'max:255'],
            'price_from' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'status' => ['required', 'in:'.implode(',', array_map(fn ($status) => $status->value, PackageStatus::cases()))],
            'is_featured' => ['nullable', 'boolean'],
            'days' => ['nullable', 'array'],
            'days.*.title' => ['required_with:days', 'string', 'max:255'],
            'days.*.description' => ['nullable', 'string'],
            'days.*.location' => ['nullable', 'string', 'max:255'],
            'days.*.meals' => ['nullable', 'string', 'max:255'],
            'days.*.overnight' => ['nullable', 'string', 'max:255'],
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

        if ($request->has('days')) {
            $dayRows = $request->input('days', []);
            $package->itineraryDays()->delete();

            foreach ($dayRows as $index => $day) {
                $package->itineraryDays()->create([
                    'day_number' => $index + 1,
                    'title' => $day['title'],
                    'description' => $day['description'] ?? null,
                    'location' => $day['location'] ?? null,
                    'meals' => $day['meals'] ?? null,
                    'overnight' => $day['overnight'] ?? null,
                ]);
            }
        }

        return redirect()->route('agency.packages.edit', ['package' => $package->id])->with('status', __('messages.package_updated', ['title' => $package->title]));
    }

    public function destroyPackage(Package $package): RedirectResponse
    {
        $this->authorize('delete', $package);
        $this->ensurePackageOwnership($package);
        $package->delete();

        return redirect()->route('agency.packages.index')->with('status', __('messages.package_deleted', ['title' => $package->title]));
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
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->appends($request->query());

        return view('agencies.packages.index', [
            'agency' => $agency,
            'packages' => $packages,
            'search' => $search,
            'status' => $status,
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
                $replacement->update(['is_primary' => true]);
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

    public function storePackageDay(Request $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meals' => ['nullable', 'string', 'max:255'],
            'overnight' => ['nullable', 'string', 'max:255'],
        ]);

        $nextDay = $package->itineraryDays()->max('day_number') + 1;

        $package->itineraryDays()->create([
            'day_number' => $nextDay,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'meals' => $data['meals'] ?? null,
            'overnight' => $data['overnight'] ?? null,
        ]);

        return back()->with('status', __('messages.itinerary_day_added'));
    }

    public function updatePackageDay(Request $request, Package $package, PackageItineraryDay $day): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        abort_unless($day->package_id === $package->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'meals' => ['nullable', 'string', 'max:255'],
            'overnight' => ['nullable', 'string', 'max:255'],
        ]);

        $day->update($data);

        return back()->with('status', __('messages.itinerary_day_updated'));
    }

    public function deletePackageDay(Package $package, PackageItineraryDay $day): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        abort_unless($day->package_id === $package->id, 404);

        $day->delete();

        return back()->with('status', __('messages.itinerary_day_deleted'));
    }

    public function movePackageDay(Request $request, Package $package, PackageItineraryDay $day): RedirectResponse
    {
        $this->authorize('update', $package);
        $this->ensurePackageOwnership($package);
        abort_unless($day->package_id === $package->id, 404);

        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $days = $package->itineraryDays()->orderBy('day_number')->orderBy('id')->get();
        $currentIndex = $days->search(fn (PackageItineraryDay $itineraryDay) => (int) $itineraryDay->id === (int) $day->id);

        if ($currentIndex === false) {
            abort(404);
        }

        $targetIndex = $data['direction'] === 'up' ? $currentIndex - 1 : $currentIndex + 1;

        if ($targetIndex < 0 || $targetIndex >= $days->count()) {
            return back();
        }

        $orderedDays = $days->values()->all();
        [$orderedDays[$currentIndex], $orderedDays[$targetIndex]] = [$orderedDays[$targetIndex], $orderedDays[$currentIndex]];

        DB::transaction(function () use ($orderedDays): void {
            foreach ($orderedDays as $index => $itineraryDay) {
                $itineraryDay->updateQuietly(['day_number' => $index + 1000]);
            }

            foreach ($orderedDays as $index => $itineraryDay) {
                $itineraryDay->updateQuietly(['day_number' => $index + 1]);
            }
        });

        return back()->with('status', __('messages.itinerary_day_updated'));
    }
}
