<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyDiscoveryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $search = trim($request->string('search')->toString());

        $agencyQuery = Agency::verified()
            ->with(['countries:id,name', 'services:id,name'])
            ->withCount(['reviews as published_reviews_count' => fn (Builder $query) => $query->where('status', 'published')])
            ->withAvg(['reviews as published_rating' => fn (Builder $query) => $query->where('status', 'published')], 'rating');

        if ($search !== '') {
            $searchVariants = [$search];
            $searchCharacters = mb_strlen($search) <= 64
                ? (preg_split('//u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [])
                : [];

            for ($index = 0; $index < count($searchCharacters) - 1; $index++) {
                $transposedCharacters = $searchCharacters;
                [$transposedCharacters[$index], $transposedCharacters[$index + 1]] = [$transposedCharacters[$index + 1], $transposedCharacters[$index]];
                $searchVariants[] = implode('', $transposedCharacters);
            }

            $agencyQuery->where(function (Builder $query) use ($search, $searchVariants): void {
                foreach ($searchVariants as $searchVariant) {
                    $query->orWhere('company_name', 'like', '%'.$searchVariant.'%');
                }

                $likeSearch = '%'.$search.'%';

                $query->orWhere('description', 'like', $likeSearch)
                    ->orWhereHas('countries', fn (Builder $countryQuery) => $countryQuery->where('countries.name', 'like', $likeSearch))
                    ->orWhereHas('services', fn (Builder $serviceQuery) => $serviceQuery->where('services.name', 'like', $likeSearch));
            });
        }

        $agencies = $agencyQuery
            ->orderByDesc('trust_score')
            ->orderBy('company_name')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $savedAgencyNames = $user->favoriteAgencies()
            ->whereIn('agencies.id', $agencies->getCollection()->modelKeys())
            ->pluck('company_name')
            ->all();

        return view('discover', [
            'agencies' => $agencies,
            'savedAgencyNames' => $savedAgencyNames,
        ]);
    }
}
