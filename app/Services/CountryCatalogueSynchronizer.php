<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class CountryCatalogueSynchronizer
{
    private const CACHE_KEY = 'country-catalogue.restcountries';

    private const API_FAILURE_CACHE_KEY = 'country-catalogue.api-unavailable';

    private const DEFAULT_COUNTRIES = [
        ['name' => 'Andorra', 'code' => 'AD'],
        ['name' => 'Australia', 'code' => 'AU'],
        ['name' => 'Cameroon', 'code' => 'CM'],
        ['name' => 'Canada', 'code' => 'CA'],
        ['name' => 'France', 'code' => 'FR'],
        ['name' => 'Germany', 'code' => 'DE'],
        ['name' => 'Greece', 'code' => 'GR'],
        ['name' => 'Iceland', 'code' => 'IS'],
        ['name' => 'Italy', 'code' => 'IT'],
        ['name' => 'Japan', 'code' => 'JP'],
        ['name' => 'Morocco', 'code' => 'MA'],
        ['name' => 'Portugal', 'code' => 'PT'],
        ['name' => 'Spain', 'code' => 'ES'],
        ['name' => 'Switzerland', 'code' => 'CH'],
        ['name' => 'Thailand', 'code' => 'TH'],
        ['name' => 'United Kingdom', 'code' => 'GB'],
        ['name' => 'United States', 'code' => 'US'],
    ];

    /**
     * @return array<int, array{name: string, code: string}>
     */
    private function countriesFromApi(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function (): array {
            $payload = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->get('https://restcountries.com/v3.1/all?fields=name,cca2')
                ->throw()
                ->json();

            if (! is_array($payload)) {
                throw new UnexpectedValueException('The country API returned an invalid response.');
            }

            $countries = collect($payload)
                ->map(function (mixed $country): ?array {
                    if (! is_array($country)
                        || ! is_array($country['name'] ?? null)
                        || ! is_string($country['name']['common'] ?? null)
                        || ! is_string($country['cca2'] ?? null)
                        || ! preg_match('/\A[A-Za-z]{2}\z/', $country['cca2'])) {
                        return null;
                    }

                    return [
                        'name' => $country['name']['common'],
                        'code' => strtoupper($country['cca2']),
                    ];
                })
                ->filter()
                ->unique('code')
                ->values()
                ->all();

            if ($countries === []) {
                throw new UnexpectedValueException('The country API did not return any valid countries.');
            }

            return $countries;
        });
    }

    public function synchronize(): bool
    {
        if (Cache::get(self::API_FAILURE_CACHE_KEY, false)) {
            $this->saveCountries($this->offlineCountries());

            return false;
        }

        try {
            $countries = $this->countriesFromApi();
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            Cache::put(self::API_FAILURE_CACHE_KEY, true, now()->addMinutes(15));
            Log::warning('Country catalogue API is unavailable; using the locally saved country list.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->saveCountries($this->offlineCountries());

            return false;
        }

        Cache::forget(self::API_FAILURE_CACHE_KEY);
        $this->saveCountries($countries);

        return true;
    }

    /**
     * @param  array<int, array{name: string, code: string}>  $countries
     */
    private function saveCountries(array $countries): void
    {
        $now = now();
        $rows = array_map(fn (array $country): array => [
            ...$country,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $countries);
        $existingByCode = DB::table('countries')->pluck('name', 'code')->all();
        $existingByName = DB::table('countries')->pluck('code', 'name')->all();
        $rows = array_values(array_filter($rows, function (array $country) use (&$existingByCode, &$existingByName): bool {
            $codeName = $existingByCode[$country['code']] ?? null;
            $nameCode = $existingByName[$country['name']] ?? null;

            if ($nameCode !== null && $nameCode !== $country['code']) {
                return false;
            }

            if ($codeName !== null && $nameCode !== null && $nameCode !== $country['code']) {
                return false;
            }

            $existingByCode[$country['code']] = $country['name'];
            $existingByName[$country['name']] = $country['code'];

            return true;
        }));

        DB::transaction(function () use ($rows): void {
            $existingCountries = DB::table('countries')
                ->whereIn('code', array_column($rows, 'code'))
                ->get(['id', 'code', 'name', 'is_active'])
                ->keyBy('code');

            foreach (array_chunk($rows, 100) as $countryBatch) {
                foreach ($countryBatch as $country) {
                    $existingCountry = $existingCountries->get($country['code']);

                    if ($existingCountry === null) {
                        DB::table('countries')->insert([
                            'name' => $country['name'],
                            'code' => $country['code'],
                            'is_active' => true,
                            'created_at' => $country['created_at'],
                            'updated_at' => $country['updated_at'],
                        ]);

                        continue;
                    }

                    $updates = [
                        'name' => $country['name'],
                        'updated_at' => $country['updated_at'],
                    ];

                    if ($existingCountry->is_active) {
                        $updates['is_active'] = true;
                    }

                    DB::table('countries')
                        ->where('id', $existingCountry->id)
                        ->update($updates);
                }
            }
        });
    }

    /**
     * @return array<int, array{name: string, code: string}>
     */
    private function offlineCountries(): array
    {
        if (! extension_loaded('intl')) {
            return self::DEFAULT_COUNTRIES;
        }

        $regionBundle = \ResourceBundle::create('en', 'ICUDATA-region');
        $regions = $regionBundle instanceof \ResourceBundle
            ? $regionBundle->get('Countries')
            : null;
        $excludedCodes = ['AC', 'CP', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'ZZ'];
        $countries = [];

        if (is_iterable($regions)) {
            foreach ($regions as $code => $name) {
                if (preg_match('/\A[A-Z]{2}\z/', (string) $code)
                    && ! in_array($code, $excludedCodes, true)
                    && is_string($name)) {
                    $countries[] = ['name' => $name, 'code' => $code];
                }
            }
        }

        if ($countries === []) {
            return self::DEFAULT_COUNTRIES;
        }

        return $countries;
    }
}
