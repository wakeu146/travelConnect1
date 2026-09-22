<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FavoriteController;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('public-home');
})->name('home');

Route::get('/discover', function () {
    /** @var User $user */
    $user = Auth::user();
    $search = trim((string) request('search'));
    $agencies = Agency::verified()->with(['countries', 'services'])
        ->withCount(['reviews as published_reviews_count' => fn ($query) => $query->where('status', 'published')])
        ->withAvg(['reviews as published_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
        ->when($search !== '', function ($query) use ($search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('company_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('countries', fn ($countryQuery) => $countryQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('name', 'like', "%{$search}%"));
            });
        })->when($search === '', function ($query): void {
            $query->whereIn('company_name', ['Atlas Horizon Voyages', 'Lumiere Routes', 'Northstar Escapes']);
        })->orderByDesc('published_reviews_count')->orderByDesc('trust_score')->when($search === '', fn ($query) => $query->take(3))->get();
    return view('discover', ['agencies' => $agencies, 'savedAgencyNames' => $user->favoriteAgencies()->pluck('company_name')->all()]);
})->middleware('auth')->name('discover');
Route::get('/account/discover', function () {
    /** @var User $user */
    $user = Auth::user();
    $search = trim((string) request('search'));
    $agencies = Agency::verified()->with(['countries', 'services'])
        ->withCount(['reviews as published_reviews_count' => fn ($query) => $query->where('status', 'published')])
        ->withAvg(['reviews as published_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
        ->when($search !== '', function ($query) use ($search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('company_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('countries', fn ($countryQuery) => $countryQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('services', fn ($serviceQuery) => $serviceQuery->where('name', 'like', "%{$search}%"));
            });
        })->when($search === '', function ($query): void {
            $query->whereIn('company_name', ['Atlas Horizon Voyages', 'Lumiere Routes', 'Northstar Escapes']);
        })->orderByDesc('published_reviews_count')->orderByDesc('trust_score')->when($search === '', fn ($query) => $query->take(3))->get();
    return view('discover', ['agencies' => $agencies, 'savedAgencyNames' => $user->favoriteAgencies()->pluck('company_name')->all()]);
})->middleware('auth')->name('account.discover');
Route::get('/agencies/{slug}', function (string $slug) {
    $fallbackAgencies = [
        'atlas-horizon-voyages' => ['name' => 'Atlas Horizon Voyages', 'country' => 'France', 'service' => 'Adventure travel', 'rating' => '4.9', 'reviews' => '38', 'description' => 'Tailor-made adventures across Europe and beyond, planned by a team that knows the routes personally.', 'cover' => asset('images/agencies/atlas-horizon.jpg')],
        'lumiere-routes' => ['name' => 'Lumiere Routes', 'country' => 'Portugal', 'service' => 'Luxury escapes', 'rating' => '4.8', 'reviews' => '31', 'description' => 'Slow travel and beautiful escapes, thoughtfully planned around the details that make a journey memorable.', 'cover' => asset('images/agencies/lumiere-routes.jpg')],
        'northstar-escapes' => ['name' => 'Northstar Escapes', 'country' => 'Canada', 'service' => 'Family holidays', 'rating' => '4.9', 'reviews' => '44', 'description' => 'Family journeys built around practical planning, local insight, and the moments that matter.', 'cover' => asset('images/agencies/northstar-escapes.jpg')],
        'sirocco-travel-house' => ['name' => 'Sirocco Travel House', 'country' => 'Morocco', 'service' => 'Cultural tours', 'rating' => '4.7', 'reviews' => '26', 'description' => 'Cultural journeys shaped by local knowledge, meaningful encounters, and carefully chosen routes.', 'cover' => asset('images/agencies/sirocco-travel.jpg')],
        'blue-fern-expeditions' => ['name' => 'Blue Fern Expeditions', 'country' => 'New Zealand', 'service' => 'Adventure travel', 'rating' => '4.8', 'reviews' => '22', 'description' => 'Outdoor journeys shaped by local expertise, flexible planning, and unforgettable landscapes.', 'cover' => asset('images/agencies/blue-fern.jpg')],
    ];

    $agencyCountries = [
        'atlas-horizon-voyages' => ['France', 'Switzerland', 'Italy'],
        'lumiere-routes' => ['Portugal', 'Spain', 'Greece'],
        'northstar-escapes' => ['Canada', 'Japan', 'Switzerland'],
        'sirocco-travel-house' => ['Morocco', 'Spain', 'Portugal'],
        'blue-fern-expeditions' => ['New Zealand', 'Australia', 'Fiji'],
    ];

    /** @var User $user */
    $user = Auth::user();
    $agencyModel = Agency::query()
        ->with(['countries', 'services', 'experiences' => fn ($query) => $query->latest('published_at')])
        ->get()
        ->first(fn (Agency $agency) => Str::slug($agency->company_name) === $slug);

    if (! $agencyModel && ! isset($fallbackAgencies[$slug])) {
        abort(404);
    }

    $agencyData = $agencyModel
        ? [
            'name' => $agencyModel->company_name,
            'country' => $agencyModel->countries->first()?->name ?? 'Worldwide',
            'service' => $agencyModel->services->first()?->name ?? 'Travel planning',
            'rating' => (string) number_format((float) ($agencyModel->reviews()->where('status', 'published')->avg('rating') ?? 0), 1),
            'reviews' => (string) ($agencyModel->reviews()->where('status', 'published')->count() ?? 0),
            'description' => $agencyModel->description ?: 'Travel planning with local expertise.',
            'cover' => $agencyModel->cover_image_path ? asset('storage/'.$agencyModel->cover_image_path) : asset('images/agencies/default-agency.jpg'),
            'countries' => $agencyModel->countries->pluck('name')->all() ?: ['Worldwide'],
        ]
        : $fallbackAgencies[$slug];

    $agencyData['countries'] = $agencyModel ? $agencyData['countries'] : ($agencyCountries[$slug] ?? [$agencyData['country']]);

    $publishedReviewCount = $agencyModel?->reviews()->where('status', 'published')->count() ?? 0;
    $publishedReviews = $agencyModel?->reviews()->with('user')->where('status', 'published')->orderByDesc('rating')->orderByDesc('created_at')->limit(5)->get() ?? collect();
    $visibleReviewCount = $publishedReviewCount ?: (int) ($agencyData['reviews'] ?? 0);
    $visibleRating = $publishedReviewCount ? number_format($agencyModel->reviews()->where('status', 'published')->avg('rating') ?? 0, 1) : ($agencyData['rating'] ?? '0');
    $ratingCounts = $agencyModel && $publishedReviewCount ? $agencyModel->reviews()->where('status', 'published')->get()->groupBy('rating')->map->count() : collect();
    $pendingReview = $agencyModel?->reviews()->where('user_id', $user->id)->where('status', 'pending')->latest()->first();

    return view('agency-show', ['agency' => $agencyData, 'agencyModel' => $agencyModel, 'isSaved' => $agencyModel && $user->favoriteAgencies()->whereKey($agencyModel->id)->exists(), 'publishedReviews' => $publishedReviews, 'publishedReviewCount' => $publishedReviewCount, 'visibleReviewCount' => $visibleReviewCount, 'visibleRating' => $visibleRating, 'ratingCounts' => $ratingCounts, 'pendingReview' => $pendingReview]);
})->middleware('auth')->name('agency.show');
Route::post('/agencies/{slug}/reviews', [ReviewController::class, 'store'])->middleware('auth')->name('reviews.store');
Route::post('/agencies/{slug}/favorite', [FavoriteController::class, 'toggle'])->middleware('auth')->name('favorites.toggle');
Route::view('/how-it-works', 'how-it-works')->name('how-it-works');
Route::view('/for-agencies', 'for-agencies')->name('for-agencies');
Route::get('/terms', fn () => view('static-page', ['pageTitle' => __('messages.terms_of_use'), 'pageIntro' => __('messages.terms_intro'), 'pageBody' => __('messages.terms_body')]))->name('terms');
Route::get('/privacy', fn () => view('static-page', ['pageTitle' => __('messages.privacy_policy'), 'pageIntro' => __('messages.privacy_intro'), 'pageBody' => __('messages.privacy_body')]))->name('privacy');
Route::get('/accessibility', fn () => view('static-page', ['pageTitle' => __('messages.accessibility'), 'pageIntro' => __('messages.accessibility_intro'), 'pageBody' => __('messages.accessibility_body')]))->name('accessibility');
Route::view('/login', 'auth.login')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
Route::view('/register', 'auth.account-type')->name('register');
Route::view('/register/agency', 'auth.register')->name('register.agency');
Route::post('/register/agency', [AuthController::class, 'registerAgency'])->middleware('throttle:register')->name('register.agency.store');
Route::view('/register/traveler', 'auth.traveler-register')->name('register.traveler');
Route::post('/register/traveler', [AuthController::class, 'registerTraveler'])->middleware('throttle:register')->name('register.traveler.store');
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:password.email')->name('password.email');
Route::get('/verify-reset-code', [AuthController::class, 'showCodeForm'])->name('password.code');
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode'])->middleware('throttle:password.code')->name('password.code.verify');
Route::get('/reset-password', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password.reset')->name('password.update');
Route::view('/login-successful', 'auth.success')->middleware('auth')->name('auth.success');
Route::middleware(['auth', 'agency-owner'])->prefix('agency')->name('agency.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\AgencyController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [\App\Http\Controllers\AgencyController::class, 'profile'])->name('profile');
    Route::put('/profile', [\App\Http\Controllers\AgencyController::class, 'updateProfile'])->name('settings.update');
    Route::post('/documents', [\App\Http\Controllers\AgencyController::class, 'storeDocument'])->name('documents.store');
    Route::post('/experiences', [\App\Http\Controllers\AgencyController::class, 'storeExperience'])->name('experiences.store');
    Route::get('/packages', [\App\Http\Controllers\AgencyController::class, 'indexPackages'])->name('packages.index');
    Route::get('/packages/create', [\App\Http\Controllers\AgencyController::class, 'createPackage'])->name('packages.create');
    Route::post('/packages', [\App\Http\Controllers\AgencyController::class, 'storePackage'])->name('packages.store');
    Route::get('/packages/{package}/edit', [\App\Http\Controllers\AgencyController::class, 'editPackage'])->name('packages.edit');
    Route::put('/packages/{package}', [\App\Http\Controllers\AgencyController::class, 'updatePackage'])->name('packages.update');
    Route::delete('/packages/{package}', [\App\Http\Controllers\AgencyController::class, 'destroyPackage'])->name('packages.destroy');
    Route::post('/packages/{package}/images', [\App\Http\Controllers\AgencyController::class, 'uploadPackageImage'])->name('packages.images.store');
    Route::delete('/packages/{package}/images/{image}', [\App\Http\Controllers\AgencyController::class, 'deletePackageImage'])->name('packages.images.destroy');
    Route::post('/packages/{package}/images/{image}/primary', [\App\Http\Controllers\AgencyController::class, 'setPrimaryPackageImage'])->name('packages.images.primary');
    Route::post('/packages/{package}/days', [\App\Http\Controllers\AgencyController::class, 'storePackageDay'])->name('packages.days.store');
    Route::post('/packages/{package}/days/{day}/move', [\App\Http\Controllers\AgencyController::class, 'movePackageDay'])->name('packages.days.move');
    Route::put('/packages/{package}/days/{day}', [\App\Http\Controllers\AgencyController::class, 'updatePackageDay'])->name('packages.days.update');
    Route::delete('/packages/{package}/days/{day}', [\App\Http\Controllers\AgencyController::class, 'deletePackageDay'])->name('packages.days.destroy');
});

Route::get('/dashboard', function () {
    /** @var User $user */
    $user = Auth::user();

    if (! $user->isTraveler()) {
        return view('auth.dashboard');
    }

    return view('auth.dashboard-overview', [
        'user' => $user,
        'savedAgencies' => $user->favoriteAgencies()
            ->with(['countries', 'services'])
            ->latest('favorites.created_at')
            ->take(3)
            ->get(),
        'savedAgencyCount' => $user->favoriteAgencies()->count(),
        'openInquiryCount' => $user->inquiries()->whereIn('status', ['open', 'responded'])->count(),
        'publishedReviewCount' => $user->reviews()->count(),
        'recentInquiries' => $user->inquiries()->with('agency')->latest()->take(3)->get(),
        'recentReviews' => $user->reviews()->with('agency')->latest()->take(3)->get(),
        'recommendedAgencies' => Agency::verified()->latest()->take(3)->get(),
    ]);
})->middleware('auth')->name('dashboard');
Route::view('/settings', 'auth.settings')->middleware('auth')->name('settings');
Route::put('/settings/profile', [AuthController::class, 'updateProfile'])->middleware('auth')->name('settings.profile');
Route::post('/settings/profile-photo', [AuthController::class, 'updateProfilePhoto'])->middleware('auth')->name('settings.profile-photo');
Route::get('/account/saved', function () {
    /** @var User $user */
    $user = Auth::user();

    return view('auth.saved', [
        'user' => $user,
        'savedAgencies' => $user->favoriteAgencies()->with(['countries', 'services', 'owner'])->withAvg(['reviews as published_rating' => fn ($query) => $query->where('status', 'published')], 'rating')->latest('favorites.created_at')->get(),
    ]);
})->middleware('auth')->name('account.saved');
Route::get('/account/activity', function () {
    /** @var User $user */
    $user = Auth::user();

    return view('auth.activity', [
        'user' => $user,
        'inquiries' => $user->inquiries()->with('agency')->latest()->get(),
        'reviews' => $user->reviews()->with('agency')->latest()->get(),
    ]);
})->middleware('auth')->name('account.activity');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/reset-blade', function() {
    return view('emails/password-reset-code');
});