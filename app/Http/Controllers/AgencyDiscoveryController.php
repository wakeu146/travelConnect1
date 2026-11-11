<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgencyDiscoveryController extends Controller
{
    private const MIN_QUERY_LENGTH = 1;

    private const RESULTS_PER_PAGE = 12;

    private const RECENT_AGENCY_LIMIT = 12;

    private const POPULAR_RATING_MINIMUM = 4.0;

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isTraveler(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'search_mode' => ['sometimes', 'boolean'],
        ]);
        $search = trim($filters['search'] ?? '');
        $location = trim($filters['location'] ?? '');
        $hasActiveSearch = mb_strlen($search) >= self::MIN_QUERY_LENGTH
            || mb_strlen($location) >= self::MIN_QUERY_LENGTH;
        $searchMode = $hasActiveSearch || ($filters['search_mode'] ?? false);

        $results = $hasActiveSearch
            ? $this->searchQuery($search, $location)
                ->paginate(self::RESULTS_PER_PAGE)
                ->withQueryString()
            : null;
        $resultAgencies = $results?->getCollection() ?? collect();
        $featuredAgencies = $this->withCardData(Agency::query())
            ->orderByDesc('trust_score')
            ->orderBy('company_name')
            ->orderBy('id')
            ->get();
        $popularAgencies = $featuredAgencies
            ->filter(fn (Agency $agency): bool => $agency->published_rating !== null
                && (float) $agency->published_rating >= self::POPULAR_RATING_MINIMUM)
            ->values();

        $recentRecords = $user->recentAgencies()
            ->whereHas('agency', fn (Builder $query) => $query->verified())
            ->with(['agency' => fn (BelongsTo $agency) => $this->withCardData($agency->getQuery())])
            ->orderByDesc('last_viewed_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_AGENCY_LIMIT)
            ->get();
        $recentAgencies = $recentRecords->pluck('agency')->filter()->values();
        $savedAgencyIds = $this->savedAgencyIds(
            $user,
            $resultAgencies->merge($recentAgencies)->merge($featuredAgencies),
        );
        $selectedAgencyId = $user->selected_agency_id === null
            ? null
            : (int) $user->selected_agency_id;

        return view('discover', [
            'hasActiveSearch' => $hasActiveSearch,
            'searchMode' => $searchMode,
            'hasLocationSearch' => mb_strlen($location) >= self::MIN_QUERY_LENGTH,
            'agencyCards' => $this->agencyCards($resultAgencies, $savedAgencyIds, $selectedAgencyId),
            'featuredAgencyCards' => $this->agencyCards($featuredAgencies, $savedAgencyIds, $selectedAgencyId),
            'recentAgencyCards' => $this->agencyCards($recentAgencies, $savedAgencyIds, $selectedAgencyId),
            'popularAgencyCards' => $this->agencyCards($popularAgencies, $savedAgencyIds, $selectedAgencyId),
            'results' => $results,
        ]);
    }

    public function search(Request $request): JsonResponse|View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isTraveler(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');
        $location = trim($filters['location'] ?? '');
        $hasActiveSearch = mb_strlen($search) >= self::MIN_QUERY_LENGTH
            || mb_strlen($location) >= self::MIN_QUERY_LENGTH;

        $results = $hasActiveSearch
            ? $this->searchQuery($search, $location)->paginate(
                self::RESULTS_PER_PAGE,
                ['*'],
                'page',
                (int) ($filters['page'] ?? 1),
            )->withQueryString()
            : null;

        if ($results !== null && ! $request->expectsJson()) {
            $results->setPath(route('discover', ['lang' => app()->getLocale()]));
        }

        $resultAgencies = $results?->getCollection() ?? collect();
        $agencyCards = $this->agencyCards(
            $resultAgencies,
            $this->savedAgencyIds($user, $resultAgencies),
            $user->selected_agency_id === null ? null : (int) $user->selected_agency_id,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $agencyCards,
                'meta' => [
                    'current_page' => $results?->currentPage() ?? 1,
                    'last_page' => $results?->lastPage() ?? 1,
                    'has_more' => $results?->hasMorePages() ?? false,
                ],
            ]);
        }

        return view('discover.search-results', [
            'agencyCards' => $agencyCards,
            'hasActiveSearch' => $hasActiveSearch,
            'hasLocationSearch' => mb_strlen($location) >= self::MIN_QUERY_LENGTH,
            'results' => $results,
        ]);
    }

    public function select(Request $request, Agency $agency): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isTraveler(), 403);
        abort_unless(
            Agency::query()->verified()->whereKey($agency->getKey())->exists(),
            404,
        );

        if ($user->selected_agency_id !== $agency->getKey()) {
            $user->selected_agency_id = $agency->getKey();
            $user->save();
        }

        return response()->json(['selected_agency_id' => (int) $agency->getKey()]);
    }

    public function destroyRecent(Request $request, Agency $agency): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isTraveler(), 403);

        $user->recentAgencies()->where('agency_id', $agency->id)->delete();

        return response()->json([], 204);
    }

    public function clearRecent(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isTraveler(), 403);

        $user->recentAgencies()->delete();

        return response()->json([], 204);
    }

    private function searchQuery(string $search, string $location): Builder
    {
        $query = $this->withCardData(Agency::query());

        if (mb_strlen($search) >= self::MIN_QUERY_LENGTH) {
            $searchVariants = [$search];

            if (mb_strlen($search) <= 64) {
                $characters = preg_split('//u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                for ($index = 0; $index < count($characters) - 1; $index++) {
                    $transposedCharacters = $characters;
                    [$transposedCharacters[$index], $transposedCharacters[$index + 1]] = [$transposedCharacters[$index + 1], $transposedCharacters[$index]];
                    $searchVariants[] = implode('', $transposedCharacters);
                }
            }

            $query->where(function (Builder $query) use ($searchVariants, $search): void {
                foreach ($searchVariants as $searchVariant) {
                    $query->orWhere('company_name', 'like', '%'.$searchVariant.'%');
                }

                $searchTerm = '%'.$search.'%';
                $query->orWhere('address', 'like', $searchTerm)
                    ->orWhereHas('countries', fn (Builder $countryQuery) => $countryQuery->where('countries.name', 'like', $searchTerm))
                    ->orWhereHas('services', fn (Builder $serviceQuery) => $serviceQuery->where('services.name', 'like', $searchTerm));
            });
        }

        if (mb_strlen($location) >= self::MIN_QUERY_LENGTH) {
            $locationTerm = '%'.$location.'%';
            $query->where('address', 'like', $locationTerm)
                ->orderByRaw('CASE WHEN address LIKE ? THEN 0 ELSE 1 END', [$location.'%']);
        }

        return $query
            ->orderByDesc('trust_score')
            ->orderBy('company_name')
            ->orderBy('id');
    }

    private function withCardData(Builder $query): Builder
    {
        return $query
            ->select([
                'agencies.id',
                'agencies.company_name',
                'agencies.address',
                'agencies.verification_status',
                'agencies.operating_status',
                'agencies.trust_score',
                'agencies.logo_path',
                'agencies.cover_image_path',
                'agencies.updated_at',
            ])
            ->verified()
            ->with(['countries:id,name', 'services:id,name'])
            ->withCount([
                'reviews as published_review_count' => fn (Builder $reviewQuery) => $reviewQuery
                    ->where('status', 'published')
                    ->where('is_hidden', false),
            ])
            ->withAvg([
                'reviews as published_rating' => fn (Builder $reviewQuery) => $reviewQuery
                    ->where('status', 'published')
                    ->where('is_hidden', false),
            ], 'rating');
    }

    /**
     * @param  Collection<int, Agency>  $agencies
     * @return array<int, int>
     */
    private function savedAgencyIds(User $user, Collection $agencies): array
    {
        $agencyIds = $agencies->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($agencyIds === []) {
            return [];
        }

        return $user->favoriteAgencies()
            ->whereIn('agencies.id', $agencyIds)
            ->pluck('agencies.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Collection<int, Agency>  $agencies
     * @param  array<int, int>  $savedAgencyIds
     * @return array<int, array<string, mixed>>
     */
    private function agencyCards(Collection $agencies, array $savedAgencyIds, ?int $selectedAgencyId): array
    {
        return $agencies
            ->map(fn (Agency $agency): array => $this->agencyCard($agency, $savedAgencyIds, $selectedAgencyId))
            ->all();
    }

    /**
     * @param  array<int, int>  $savedAgencyIds
     * @return array<string, mixed>
     */
    private function agencyCard(Agency $agency, array $savedAgencyIds, ?int $selectedAgencyId): array
    {
        return [
            'id' => $agency->id,
            'slug' => Str::slug($agency->company_name),
            'name' => $agency->company_name,
            'location' => $agency->address,
            'logo_url' => $agency->logoUrl(),
            'cover_url' => $agency->coverImageUrl(),
            'verified' => true,
            'rating' => $agency->published_review_count > 0
                ? number_format((float) $agency->published_rating, 1)
                : null,
            'review_count' => $agency->published_review_count,
            'destinations' => $agency->countries->take(3)->pluck('name')->all(),
            'services' => $agency->services->take(3)->pluck('name')->all(),
            'saved' => in_array($agency->id, $savedAgencyIds, true),
            'selected' => $agency->id === $selectedAgencyId,
            'profile_url' => route('agency.show', [
                'slug' => Str::slug($agency->company_name),
                'lang' => app()->getLocale(),
            ]),
            'favorite_url' => route('favorites.toggle', ['slug' => Str::slug($agency->company_name)]),
            'select_url' => route('discover.selection.store', ['agency' => $agency->id]),
            'remove_recent_url' => route('discover.recent.destroy', ['agency' => $agency->id]),
        ];
    }
}
