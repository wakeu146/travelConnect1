<?php

use App\Enums\PackageStatus;
use App\Http\Controllers\AgencyAvailabilityController;
use App\Http\Controllers\AgencyBookingController;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\AgencyOperationsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ReviewController;
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
    $normalizedSearch = strtolower(preg_replace('/\s+/', ' ', $search) ?? $search);
    $agencies = Agency::verified()->with(['countries', 'services', 'reviews'])
        ->withCount(['reviews as published_reviews_count' => fn ($query) => $query->where('status', 'published')])
        ->withAvg(['reviews as published_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
        ->orderByDesc('trust_score')
        ->get();

    $matchesSearch = function (Agency $agency) use ($normalizedSearch): bool {
        if ($normalizedSearch === '') {
            return true;
        }

        $searchableValues = [
            strtolower($agency->company_name),
            strtolower($agency->description ?? ''),
            strtolower($agency->email ?? ''),
            strtolower(Str::slug($agency->company_name)),
            ...$agency->countries->pluck('name')->map(fn ($country) => strtolower((string) $country))->all(),
            ...$agency->services->pluck('name')->map(fn ($service) => strtolower((string) $service))->all(),
        ];

        foreach ($searchableValues as $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            if (str_contains($value, $normalizedSearch)) {
                return true;
            }

            foreach (preg_split('/[\s_\-]+/', $value) as $token) {
                $token = strtolower((string) $token);
                if ($token === '') {
                    continue;
                }

                if (str_contains($token, $normalizedSearch)) {
                    return true;
                }

                $score = 0;
                similar_text($token, $normalizedSearch, $score);
                if ($score >= 70) {
                    return true;
                }
            }

            $score = 0;
            similar_text($value, $normalizedSearch, $score);
            if ($score >= 80) {
                return true;
            }
        }

        return false;
    };

    if ($normalizedSearch !== '') {
        $agencies = $agencies->filter($matchesSearch);
    } else {
        $agencies = $agencies->filter(fn (Agency $agency) => in_array($agency->company_name, ['Atlas Horizon Voyages', 'Lumiere Routes', 'Northstar Escapes'], true))->take(3);
    }

    return view('discover', ['agencies' => $agencies, 'savedAgencyNames' => $user->favoriteAgencies()->pluck('company_name')->all()]);
})->middleware('auth')->name('discover');
Route::get('/account/discover', function () {
    /** @var User $user */
    $user = Auth::user();
    $search = trim((string) request('search'));
    $normalizedSearch = strtolower(preg_replace('/\s+/', ' ', $search) ?? $search);
    $agencies = Agency::verified()->with(['countries', 'services', 'reviews'])
        ->withCount(['reviews as published_reviews_count' => fn ($query) => $query->where('status', 'published')])
        ->withAvg(['reviews as published_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
        ->orderByDesc('trust_score')
        ->get();

    $matchesSearch = function (Agency $agency) use ($normalizedSearch): bool {
        if ($normalizedSearch === '') {
            return true;
        }

        $searchableValues = [
            strtolower($agency->company_name),
            strtolower($agency->description ?? ''),
            strtolower($agency->email ?? ''),
            strtolower(Str::slug($agency->company_name)),
            ...$agency->countries->pluck('name')->map(fn ($country) => strtolower((string) $country))->all(),
            ...$agency->services->pluck('name')->map(fn ($service) => strtolower((string) $service))->all(),
        ];

        foreach ($searchableValues as $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            if (str_contains($value, $normalizedSearch)) {
                return true;
            }

            foreach (preg_split('/[\s_\-]+/', $value) as $token) {
                $token = strtolower((string) $token);
                if ($token === '') {
                    continue;
                }

                if (str_contains($token, $normalizedSearch)) {
                    return true;
                }

                $score = 0;
                similar_text($token, $normalizedSearch, $score);
                if ($score >= 70) {
                    return true;
                }
            }

            $score = 0;
            similar_text($value, $normalizedSearch, $score);
            if ($score >= 80) {
                return true;
            }
        }

        return false;
    };

    if ($normalizedSearch !== '') {
        $agencies = $agencies->filter($matchesSearch);
    } else {
        $agencies = $agencies->filter(fn (Agency $agency) => in_array($agency->company_name, ['Atlas Horizon Voyages', 'Lumiere Routes', 'Northstar Escapes'], true))->take(3);
    }

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

    if ($agencyModel) {
        $agencyModel->load(['packages' => fn ($query) => $query
            ->where('status', PackageStatus::PUBLISHED->value)
            ->with([
                'images',
                'availabilities' => fn ($availabilityQuery) => $availabilityQuery
                    ->where('status', 'active')
                    ->whereDate('departure_date', '>=', now()->toDateString())
                    ->orderBy('departure_date'),
            ])]);
    }

    $agencyData = $agencyModel
        ? [
            'name' => $agencyModel->company_name,
            'country' => $agencyModel->countries->first()?->name ?? 'Worldwide',
            'service' => $agencyModel->services->first()?->name ?? 'Travel planning',
            'rating' => (string) number_format((float) ($agencyModel->reviews()->where('status', 'published')->avg('rating') ?? 0), 1),
            'reviews' => (string) ($agencyModel->reviews()->where('status', 'published')->count() ?? 0),
            'description' => $agencyModel->description ?: 'Travel planning with local expertise.',
            'cover' => $agencyModel->coverImageUrl() ?? asset('images/agencies/default-agency.jpg'),
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

    return view('agency-show', ['agency' => $agencyData, 'agencyModel' => $agencyModel, 'packages' => $agencyModel?->packages ?? collect(), 'isSaved' => $agencyModel && $user->favoriteAgencies()->whereKey($agencyModel->id)->exists(), 'publishedReviews' => $publishedReviews, 'publishedReviewCount' => $publishedReviewCount, 'visibleReviewCount' => $visibleReviewCount, 'visibleRating' => $visibleRating, 'ratingCounts' => $ratingCounts, 'pendingReview' => $pendingReview]);
})->middleware('auth')->name('agency.show');
Route::post('/agencies/{slug}/inquiries', [InquiryController::class, 'store'])->middleware('auth')->name('inquiries.store');
Route::post('/agencies/{slug}/reviews', [ReviewController::class, 'store'])->middleware('auth')->name('reviews.store');
Route::post('/agencies/{slug}/favorite', [FavoriteController::class, 'toggle'])->middleware('auth')->name('favorites.toggle');
Route::post('/agency/inquiries/{inquiry}/reply', [AgencyController::class, 'replyToInquiry'])->middleware(['auth', 'agency-owner'])->name('agency.inquiries.reply');
Route::post('/agency/inquiries/{inquiry}/close', [AgencyController::class, 'closeInquiry'])->middleware(['auth', 'agency-owner'])->name('agency.inquiries.close');
Route::delete('/agency/inquiries/{inquiry}', [AgencyController::class, 'destroyInquiry'])->middleware(['auth', 'agency-owner'])->name('agency.inquiries.destroy');
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
    Route::get('/dashboard', [AgencyController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [AgencyController::class, 'profile'])->name('profile');
    Route::put('/profile', [AgencyController::class, 'updateProfile'])->name('settings.update');
    Route::post('/documents', [AgencyController::class, 'storeDocument'])->name('documents.store');
    Route::put('/documents/{document}', [AgencyController::class, 'updateDocument'])->name('documents.update');
    Route::delete('/documents/{document}', [AgencyController::class, 'destroyDocument'])->name('documents.destroy');
    Route::post('/experiences', [AgencyController::class, 'storeExperience'])->name('experiences.store');
    Route::delete('/experiences/{experience}', [AgencyController::class, 'destroyExperience'])->name('experiences.destroy');
    Route::get('/packages', [AgencyController::class, 'indexPackages'])->name('packages.index');
    Route::get('/packages/create', [AgencyController::class, 'createPackage'])->name('packages.create');
    Route::post('/packages', [AgencyController::class, 'storePackage'])->name('packages.store');
    Route::post('/packages/{package}/duplicate', [AgencyController::class, 'duplicatePackage'])->name('packages.duplicate');
    Route::get('/packages/{package}/edit', [AgencyController::class, 'editPackage'])->name('packages.edit');
    Route::put('/packages/{package}', [AgencyController::class, 'updatePackage'])->name('packages.update');
    Route::delete('/packages/{package}', [AgencyController::class, 'destroyPackage'])->name('packages.destroy');
    Route::post('/packages/{package}/images', [AgencyController::class, 'uploadPackageImage'])->name('packages.images.store');
    Route::delete('/packages/{package}/images/{image}', [AgencyController::class, 'deletePackageImage'])->name('packages.images.destroy');
    Route::post('/packages/{package}/images/{image}/primary', [AgencyController::class, 'setPrimaryPackageImage'])->name('packages.images.primary');

    Route::get('/availability', [AgencyAvailabilityController::class, 'index'])->name('availability.index');
    Route::get('/availability/create', [AgencyAvailabilityController::class, 'create'])->name('availability.create');
    Route::post('/availability', [AgencyAvailabilityController::class, 'store'])->name('availability.store');
    Route::get('/availability/{availability}/edit', [AgencyAvailabilityController::class, 'edit'])->name('availability.edit');
    Route::put('/availability/{availability}', [AgencyAvailabilityController::class, 'update'])->name('availability.update');
    Route::patch('/availability/{availability}/status', [AgencyAvailabilityController::class, 'updateStatus'])->name('availability.status');

    Route::get('/bookings', [AgencyBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [AgencyBookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/confirm', [AgencyBookingController::class, 'confirm'])->name('bookings.confirm');
    Route::patch('/bookings/{booking}/reject', [AgencyBookingController::class, 'reject'])->name('bookings.reject');
    Route::patch('/bookings/{booking}/cancel', [AgencyBookingController::class, 'cancel'])->name('bookings.cancel');
    Route::patch('/bookings/{booking}/complete', [AgencyBookingController::class, 'complete'])->name('bookings.complete');

    Route::get('/inquiries', [AgencyOperationsController::class, 'inquiries'])->name('inquiries.index');
    Route::get('/customers', [AgencyOperationsController::class, 'customers'])->name('customers.index');
    Route::get('/reviews', [AgencyOperationsController::class, 'reviews'])->name('reviews.index');
    Route::get('/payments', [AgencyOperationsController::class, 'payments'])->name('payments.index');
    Route::get('/analytics', [AgencyOperationsController::class, 'analytics'])->name('analytics.index');
});

Route::get('/packages/{package}', [BookingController::class, 'packageShow'])->name('packages.show');
Route::middleware(['auth'])->get('/packages/{package}/book', [BookingController::class, 'create'])->name('bookings.create');
Route::middleware(['auth'])->prefix('bookings')->name('bookings.')->group(function () {
    Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
    Route::get('/{booking}/confirmation', [BookingController::class, 'confirmation'])->name('confirmation');
    Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
});
Route::middleware(['auth'])->post('/packages/{package}/book', [BookingController::class, 'store'])->name('bookings.store');

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
Route::get('/account/bookings', [BookingController::class, 'index'])->middleware('auth')->name('account.bookings');
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

    $user->unreadNotifications()->update(['read_at' => now()]);

    return view('auth.activity', [
        'user' => $user,
        'notifications' => $user->notifications()->latest()->limit(20)->get(),
        'notificationCount' => $user->unreadNotifications()->count(),
        'inquiries' => $user->inquiries()->with('agency')->latest()->get(),
        'reviews' => $user->reviews()->with('agency')->latest()->get(),
    ]);
})->middleware('auth')->name('account.activity');
Route::delete('/account/notifications/{notification}', function (string $notification) {
    /** @var User $user */
    $user = Auth::user();
    $user->notifications()->whereKey($notification)->firstOrFail()->delete();

    return redirect()->route('account.activity', ['lang' => app()->getLocale()]);
})->middleware('auth')->name('account.notifications.destroy');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/reset-blade', function () {
    return view('emails/password-reset-code');
});
