<?php

namespace App\Http\Controllers;

use App\Enums\PackageAvailabilityStatus;
use App\Enums\PackageStatus;
use App\Enums\ReviewStatus;
use App\Models\Agency;
use App\Models\Package;
use App\Support\CanonicalUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicDirectoryController extends Controller
{
    public function agencies(): View
    {
        $publishedReviews = static fn (Builder $query): Builder => $query
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->where('is_hidden', false);

        $agencies = Agency::verified()
            ->select(['id', 'company_name', 'description', 'logo_path', 'cover_image_path', 'updated_at'])
            ->with(['countries:id,name', 'services:id,name'])
            ->withCount(['reviews as published_reviews_count' => $publishedReviews])
            ->withAvg(['reviews as published_reviews_rating' => $publishedReviews], 'rating')
            ->orderBy('company_name')
            ->paginate(12);

        $agencies->getCollection()->transform(fn (Agency $agency): array => [
            'name' => $agency->company_name,
            'slug' => Str::slug($agency->company_name),
            'description' => $agency->description,
            'countries' => $agency->countries->pluck('name')->all(),
            'services' => $agency->services->pluck('name')->all(),
            'review_count' => $agency->published_reviews_count,
            'rating' => $agency->published_reviews_rating === null
                ? null
                : number_format((float) $agency->published_reviews_rating, 1),
            'logo' => $this->publicImageUrl($agency->logo_path),
            'cover' => $this->publicImageUrl($agency->cover_image_path),
        ]);

        return view('public.agencies.index', [
            'agencies' => $agencies,
            'title' => __('messages.public_directory.agencies.title'),
            'description' => __('messages.public_directory.agencies.description'),
        ]);
    }

    public function showAgency(string $slug): View
    {
        $publishedReviews = static fn (Builder $query): Builder => $query
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->where('is_hidden', false);
        $availableDepartures = static fn (Builder $query): Builder => $query
            ->where('status', PackageAvailabilityStatus::ACTIVE->value)
            ->whereDate('departure_date', '>', today()->toDateString());

        $agencyRecord = Agency::verified()
            ->select(['id', 'company_name', 'description', 'logo_path', 'cover_image_path', 'updated_at'])
            ->with(['countries:id,name', 'services:id,name'])
            ->withCount(['reviews as published_reviews_count' => $publishedReviews])
            ->withAvg(['reviews as published_reviews_rating' => $publishedReviews], 'rating')
            ->with(['packages' => function (Builder $query) use ($availableDepartures): void {
                $query->where('status', PackageStatus::PUBLISHED->value)
                    ->whereHas('availabilities', $availableDepartures)
                    ->with([
                        'images',
                        'availabilities' => fn (Builder $availabilityQuery): Builder => $availableDepartures($availabilityQuery)
                            ->orderBy('departure_date'),
                    ])
                    ->orderByDesc('published_at');
            }])
            ->get()
            ->first(fn (Agency $agency): bool => Str::slug($agency->company_name) === $slug);

        abort_if(! $agencyRecord, 404);

        $reviews = $agencyRecord->reviews()
            ->where('status', ReviewStatus::PUBLISHED->value)
            ->where('is_hidden', false)
            ->latest()
            ->limit(10)
            ->get(['rating', 'comment', 'created_at']);

        $agencyUrl = CanonicalUrl::absolute('/agencies/'.$slug);
        $image = $this->publicImageUrl($agencyRecord->cover_image_path);
        $reviewCount = (int) $agencyRecord->published_reviews_count;
        $rating = $agencyRecord->published_reviews_rating === null
            ? null
            : number_format((float) $agencyRecord->published_reviews_rating, 1);
        $countries = $agencyRecord->countries->pluck('name')->all();
        $services = $agencyRecord->services->pluck('name')->all();
        $schema = [
            '@type' => 'TravelAgency',
            '@id' => $agencyUrl.'#agency',
            'name' => $agencyRecord->company_name,
            'url' => $agencyUrl,
            'description' => $agencyRecord->description,
            'areaServed' => array_map(
                static fn (string $country): array => ['@type' => 'Country', 'name' => $country],
                $countries
            ),
        ];

        if ($image) {
            $schema['image'] = $image;
        }

        if ($reviewCount > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $reviewCount,
            ];
        }

        $agency = [
            'name' => $agencyRecord->company_name,
            'description' => $agencyRecord->description,
            'countries' => $countries,
            'services' => $services,
            'logo' => $this->publicImageUrl($agencyRecord->logo_path),
            'cover' => $image ?: asset('images/agencies/default-agency.jpg'),
            'rating' => $rating,
            'review_count' => $reviewCount,
        ];

        return view('public.agencies.show', [
            'agency' => $agency,
            'packages' => $agencyRecord->packages,
            'reviews' => $reviews,
            'schema' => [$schema],
            'title' => __('messages.public_directory.agency.title', ['agency' => $agency['name']]),
            'description' => __('messages.public_directory.agency.description', ['agency' => $agency['name']]),
        ]);
    }

    public function packages(): View
    {
        $availableDepartures = static fn (Builder $query): Builder => $query
            ->where('status', PackageAvailabilityStatus::ACTIVE->value)
            ->whereDate('departure_date', '>', today()->toDateString());

        $packages = Package::query()
            ->where('status', PackageStatus::PUBLISHED->value)
            ->whereHas('agency', static fn (Builder $query): Builder => $query->verified())
            ->whereHas('availabilities', $availableDepartures)
            ->with([
                'agency:id,company_name',
                'images',
                'availabilities' => fn (Builder $query): Builder => $availableDepartures($query)->orderBy('departure_date'),
            ])
            ->orderByDesc('published_at')
            ->paginate(12);

        return view('public.packages.index', [
            'packages' => $packages,
            'title' => __('messages.public_directory.packages.title'),
            'description' => __('messages.public_directory.packages.description'),
        ]);
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path) ? $disk->url($path) : null;
    }
}
