<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTravelCatalogueController extends Controller
{
    public function storeCountry(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:countries,name'],
            'code' => ['required', 'alpha:ascii', 'size:2', 'unique:countries,code'],
        ]);

        Country::query()->create($data);

        return redirect()->route('admin.dashboard.page', [
            'page' => 'countries',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_travel.country_saved'));
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $request->merge(['code' => strtoupper((string) $request->input('code'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('countries', 'name')->ignore($country)],
            'code' => ['required', 'alpha:ascii', 'size:2', Rule::unique('countries', 'code')->ignore($country)],
        ]);

        $country->update($data);

        return redirect()->route('admin.dashboard.page', [
            'page' => 'countries',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_travel.country_saved'));
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', 'unique:services,name'],
        ]);

        Service::query()->create($data);

        return redirect()->route('admin.dashboard.page', [
            'page' => 'travel_services',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_travel.service_saved'));
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('services', 'name')->ignore($service)],
        ]);

        $service->update($data);

        return redirect()->route('admin.dashboard.page', [
            'page' => 'travel_services',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_travel.service_saved'));
    }
}
