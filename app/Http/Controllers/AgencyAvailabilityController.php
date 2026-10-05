<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PackageAvailability;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgencyAvailabilityController extends Controller
{
    public function index(Request $request, BookingService $bookingService): View
    {
        $bookingService->expirePastDepartures();
        $agency = auth()->user()->agency;

        $query = PackageAvailability::query()
            ->with(['package'])
            ->whereHas('package', fn ($query) => $query->where('agency_id', $agency->id));

        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('package', fn ($packageQuery) => $packageQuery->where('title', 'like', "%{$search}%")->orWhere('destination', 'like', "%{$search}%"));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($packageId = $request->query('package_id')) {
            $query->whereHas('package', fn ($packageQuery) => $packageQuery->where('id', $packageId)->where('agency_id', $agency->id));
        }

        if ($date = $request->query('date')) {
            $query->whereDate('departure_date', $date);
        }

        $availability = $query->orderBy('departure_date')->paginate(20)->appends($request->query());
        $capacitySnapshots = $bookingService->capacitySnapshots($availability->getCollection());

        return view('agencies.availability.index', [
            'availability' => $availability,
            'agency' => $agency,
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
            'date' => $request->query('date', ''),
            'packageId' => $packageId ?? '',
            'packages' => $agency->packages()->orderBy('title')->get(['id', 'title']),
            'capacitySnapshots' => $capacitySnapshots,
        ]);
    }

    public function create(): View
    {
        $agency = auth()->user()->agency;

        return view('agencies.availability.create', [
            'agency' => $agency,
            'packages' => $agency->packages()->orderBy('title')->get(),
            'availability' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $agency = auth()->user()->agency;

        $data = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'departure_date' => ['required', 'date'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive,cancelled'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (today()->gte($data['departure_date'])) {
            return back()->withErrors(['departure_date' => __('messages.departure_date_passed')])->withInput();
        }

        $package = Package::findOrFail($data['package_id']);
        abort_unless($package->agency_id === $agency->id, 403);

        if ($agency->packages()->whereKey($package->id)->whereHas('availabilities', fn ($query) => $query->whereDate('departure_date', $data['departure_date']))->exists()) {
            return back()->withErrors(['departure_date' => __('messages.departure_date_duplicate')])->withInput();
        }

        PackageAvailability::create([
            'package_id' => $package->id,
            'departure_date' => $data['departure_date'],
            'capacity' => $data['capacity'],
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('agency.availability.index')->with('status', __('messages.availability_created'));
    }

    public function edit(PackageAvailability $availability): View
    {
        $this->authorize('update', $availability);

        return view('agencies.availability.edit', [
            'agency' => auth()->user()->agency,
            'packages' => auth()->user()->agency->packages()->orderBy('title')->get(),
            'availability' => $availability->load('package'),
        ]);
    }

    public function update(Request $request, PackageAvailability $availability): RedirectResponse
    {
        $this->authorize('update', $availability);

        $data = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'departure_date' => ['required', 'date'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive,cancelled,expired'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = Package::findOrFail($data['package_id']);
        abort_unless($package->agency_id === auth()->user()->agency->id, 403);

        if ($data['status'] === 'active' && today()->gte($data['departure_date'])) {
            return back()->withErrors(['departure_date' => __('messages.departure_date_passed')])->withInput();
        }

        if ($package->availabilities()->where('id', '!=', $availability->id)->whereDate('departure_date', $data['departure_date'])->exists()) {
            return back()->withErrors(['departure_date' => __('messages.departure_date_duplicate')])->withInput();
        }

        $availability->update($data);

        return redirect()->route('agency.availability.index')->with('status', __('messages.availability_updated'));
    }

    public function updateStatus(Request $request, PackageAvailability $availability): RedirectResponse
    {
        $this->authorize('update', $availability);

        $status = $request->validate([
            'status' => ['required', 'in:active,inactive,cancelled'],
        ])['status'];

        if ($status === 'active' && $availability->departure_date->lte(today())) {
            return back()->withErrors(['status' => __('messages.departure_date_passed')]);
        }

        $availability->update(['status' => $status]);

        return back()->with('status', __('messages.availability_status_updated'));
    }
}
