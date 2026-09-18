<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Collection;

class CountryCatalog
{
    public function all(): Collection
    {
        $countries = require base_path('vendor/umpirsky/country-list/data/en/country.php');

        return collect($countries)->map(function (string $name, string $code): Country {
            return Country::updateOrCreate(['code' => $code], ['name' => $name]);
        })->values();
    }
}
