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
    public function addCountry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
        ]);
        $country = Country::query()->where('is_active', false)->findOrFail($data['country_id']);
        $country->update(['is_active' => true]);

        return redirect()->route('admin.dashboard.page', [
            'page' => 'countries',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_travel.country_saved'));
    }

    public function removeCountry(Country $country): RedirectResponse
    {
        $country->update(['is_active' => false]);

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
