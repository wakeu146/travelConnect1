<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgencyDocumentRequest;
use App\Http\Requests\StoreAgencyExperienceRequest;
use App\Http\Requests\UpdateAgencyExperienceRequest;
use App\Http\Requests\UpdateAgencyProfileRequest;
use App\Http\Requests\StoreAgencyServiceRequest;
use App\Models\Service;
use App\Models\AgencyExperience;
use App\Services\CountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AgencyDashboardController extends Controller
{
    public function edit(Request $request, CountryCatalog $countryCatalog): View
    {
        $agency = $request->user()->agency()->with(['owner', 'countries', 'services', 'documents' => fn ($query) => $query->latest('uploaded_at')])->firstOrFail();
        Gate::authorize('viewDashboard', $agency);

        return view('agencies.settings', [
            'agency' => $agency,
            'countries' => $countryCatalog->all(),
            'services' => Service::query()->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $agency = $request->user()->agency()->with([
            'owner',
            'countries',
            'services',
            'experiences' => fn ($query) => $query->latest()->take(3),
        ])->firstOrFail();

        Gate::authorize('viewDashboard', $agency);

        $publishedReviews = $agency->reviews()
            ->with('user')
            ->where('status', 'published')
            ->latest()
            ->take(5)
            ->get();

        $profileFields = [
            $agency->company_name,
            $agency->description,
            $agency->license_number,
            $agency->address,
            $agency->phone,
            $agency->email,
            $agency->website,
            $agency->countries->isNotEmpty(),
            $agency->services->isNotEmpty(),
        ];

        return view('agencies.dashboard', [
            'agency' => $agency,
            'profileCompletion' => (int) round(collect($profileFields)->filter()->count() / count($profileFields) * 100),
            'openInquiryCount' => $agency->inquiries()->where('status', 'open')->count(),
            'respondedInquiryCount' => $agency->inquiries()->where('status', 'responded')->count(),
            'publishedReviewCount' => $agency->reviews()->where('status', 'published')->count(),
            'averageRating' => $agency->reviews()->where('status', 'published')->avg('rating'),
            'recentInquiries' => $agency->inquiries()->with('user')->latest()->take(5)->get(),
            'publishedReviews' => $publishedReviews,
        ]);
    }

    public function updateProfile(UpdateAgencyProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $agency = $user->agency;
        $data = $request->validated();
        Gate::authorize('viewDashboard', $agency);

        DB::transaction(function () use ($user, $agency, $data, $request): void {
            $user->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $agency->update([
                'company_name' => $data['company_name'], 'description' => $data['description'], 'license_number' => $data['license_number'],
                'address' => $data['address'] ?? null, 'phone' => $data['agency_phone'] ?? null, 'email' => $data['agency_email'] ?? null,
                'website' => $data['website'] ?? null,
                'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('agency-media', 'public') : $agency->logo_path,
                'cover_image_path' => $request->hasFile('cover_image') ? $request->file('cover_image')->store('agency-media', 'public') : $agency->cover_image_path,
            ]);
            $agency->countries()->sync($data['countries'] ?? []);
            $agency->services()->sync($data['services'] ?? []);
        });

        return back();
    }

    public function storeService(StoreAgencyServiceRequest $request): RedirectResponse
    {
        $agency = $request->user()->agency;
        Gate::authorize('viewDashboard', $agency);
        $service = Service::firstOrCreate(['name' => trim($request->validated('name'))]);
        $agency->services()->syncWithoutDetaching([$service->id]);

        return back();
    }

    public function storeDocument(StoreAgencyDocumentRequest $request): RedirectResponse
    {
        $agency = $request->user()->agency;
        Gate::authorize('viewDashboard', $agency);
        $agency->documents()->create(['type' => $request->validated('type'), 'file_path' => $request->file('document')->store('agency-documents'), 'status' => 'pending', 'uploaded_at' => now()]);
        return back();
    }

    public function storeExperience(StoreAgencyExperienceRequest $request): RedirectResponse
    {
        $agency = $request->user()->agency;
        Gate::authorize('viewDashboard', $agency);
        $agency->experiences()->create(['image_path' => $request->file('image')->store('agency-experiences', 'public'), 'destination' => $request->validated('destination'), 'caption' => $request->validated('caption'), 'alt_text' => $request->validated('alt_text'), 'published_at' => now()]);
        return back();
    }

    public function gallery(Request $request): View
    {
        $agency = $request->user()->agency()->with(['experiences' => fn ($query) => $query->latest()])->firstOrFail();
        Gate::authorize('viewDashboard', $agency);

        return view('agencies.gallery', ['agency' => $agency]);
    }

    public function updateExperience(UpdateAgencyExperienceRequest $request, AgencyExperience $experience): RedirectResponse
    {
        $experience->update([
            'image_path' => $request->hasFile('image') ? $request->file('image')->store('agency-experiences', 'public') : $experience->image_path,
            'destination' => $request->validated('destination'),
            'caption' => $request->validated('caption'),
            'alt_text' => $request->validated('alt_text'),
        ]);

        return back();
    }

    public function destroyExperience(Request $request, AgencyExperience $experience): RedirectResponse
    {
        Gate::authorize('viewDashboard', $experience->agency);
        $experience->delete();

        return back();
    }
}
