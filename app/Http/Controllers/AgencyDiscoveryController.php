<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgencyDiscoveryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $search = trim($request->string('search')->toString());

        $agencyQuery = $this->agencyQuery();

        if ($search !== '') {
            $this->applySearch($agencyQuery, $search);
        }

        $agencies = $agencyQuery
            ->orderByDesc('trust_score')
            ->orderBy('company_name')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $savedAgencyIds = $user->favoriteAgencies()
            ->whereIn('agencies.id', $agencies->getCollection()->modelKeys())
            ->pluck('agencies.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return view('discover', [
            'agencies' => $agencies,
            'savedAgencyIds' => $savedAgencyIds,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->merge(['search' => trim($request->string('search')->toString())]);
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:100'],
        ]);
        $search = $validated['search'];

        $agencies = $this->applyNameSearch($this->agencyQuery(), $search)
            ->orderByDesc('trust_score')
            ->orderBy('company_name')
            ->orderBy('id')
            ->get();
        $savedAgencyIds = $request->user()
            ->favoriteAgencies()
            ->whereIn('agencies.id', $agencies->modelKeys())
            ->pluck('agencies.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return response()->json([
            'count' => $agencies->count(),
            'cards_html' => view('discover.partials.agency-cards', [
                'agencies' => $agencies,
                'savedAgencyIds' => $savedAgencyIds,
            ])->render(),
            'data' => $agencies->map(fn (Agency $agency): array => [
                'name' => $agency->company_name,
                'location' => $agency->address,
                'destinations' => $agency->countries->take(2)->pluck('name')->all(),
                'service' => $agency->services->first()?->name,
                'category' => $agency->services->first()?->name,
                'rating' => $agency->published_reviews_count > 0
                    ? number_format((float) $agency->published_rating, 1)
                    : null,
                'review_count' => $agency->published_reviews_count,
                'image_url' => $agency->logoUrl() ?? $agency->coverImageUrl(),
                'saved' => in_array((int) $agency->id, $savedAgencyIds, true),
                'url' => route('agency.show', [
                    'slug' => Str::slug($agency->company_name),
                    'lang' => app()->getLocale(),
                ]),
            ])->all(),
        ]);
    }

    private function applyNameSearch(Builder $query, string $search): Builder
    {
        return $query->whereRaw('LOWER(company_name) LIKE ?', ['%'.mb_strtolower($search).'%']);
    }

    private function agencyQuery(): Builder
    {
        return Agency::verified()
            ->with(['countries:id,name', 'services:id,name'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query
                ->where('status', 'published')
                ->where('is_hidden', false)])
            ->withAvg(['reviews as published_rating' => fn (Builder $query) => $query
                ->where('status', 'published')
                ->where('is_hidden', false)], 'rating');
    }

    private function applySearch(Builder $query, string $search): Builder
    {
        $searchVariants = [$search];
        $searchCharacters = mb_strlen($search) <= 64
            ? (preg_split('//u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            : [];

        for ($index = 0; $index < count($searchCharacters) - 1; $index++) {
            $transposedCharacters = $searchCharacters;
            [$transposedCharacters[$index], $transposedCharacters[$index + 1]] = [$transposedCharacters[$index + 1], $transposedCharacters[$index]];
            $searchVariants[] = implode('', $transposedCharacters);
        }

        return $query->where(function (Builder $query) use ($search, $searchVariants): void {
            foreach ($searchVariants as $searchVariant) {
                $query->orWhere('company_name', 'like', '%'.$searchVariant.'%');
            }

            $likeSearch = '%'.$search.'%';

            $query->orWhere('description', 'like', $likeSearch)
                ->orWhereHas('countries', fn (Builder $countryQuery) => $countryQuery->where('countries.name', 'like', $likeSearch))
                ->orWhereHas('services', fn (Builder $serviceQuery) => $serviceQuery->where('services.name', 'like', $likeSearch));
        });
    }
}
