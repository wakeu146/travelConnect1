<x-agency-shell>
    <div class="mx-auto max-w-6xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.availability') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.manage_departures') }}</h1>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.back_to_dashboard') }}</a>
                    <a href="{{ route('agency.availability.create', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.add_departure') }}</a>
                </div>
            </div>

            @if (session('status'))
                <div class="agency-panel mb-6 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="agency-panel mb-6 rounded-2xl border p-4 shadow-sm">
                <form method="GET" action="{{ route('agency.availability.index', ['lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <label for="availability-search" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.search') }}</label>
                        <input id="availability-search" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('messages.package_or_destination') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                    </div>
                    <div class="lg:w-52">
                        <label for="availability-status" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.status') }}</label>
                        <select id="availability-status" name="status" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            <option value="">{{ __('messages.all') }}</option>
                            <option value="active" {{ ($status ?? '') === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                            <option value="inactive" {{ ($status ?? '') === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                            <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>{{ __('messages.cancelled') }}</option>
                        </select>
                    </div>
                    <div class="lg:w-52">
                        <label for="availability-date" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.departure_date') }}</label>
                        <input id="availability-date" type="date" name="date" value="{{ $date ?? '' }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                    </div>
                    <div class="lg:w-56">
                        <label for="availability-package" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.package') }}</label>
                        <select id="availability-package" name="package_id" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            <option value="">{{ __('messages.all_packages') }}</option>
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" {{ (string) ($packageId ?? '') === (string) $package->id ? 'selected' : '' }}>{{ $package->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.search') }}</button>
                        @if (($search ?? '') !== '' || ($status ?? '') !== '' || ($date ?? '') !== '' || ($packageId ?? '') !== '')
                            <a href="{{ route('agency.availability.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-3 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.clear_filters') }}</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                @forelse ($availability as $item)
                    <article class="agency-panel rounded-2xl border p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ $item->package?->destination ?? __('messages.travel_package') }}</p>
                                <h2 class="mt-2 text-2xl font-bold">{{ $item->package?->title ?? __('messages.package') }}</h2>
                                <p class="mt-2 text-sm text-[#607985]">{{ __('messages.departure_on_capacity', ['date' => $item->departure_date->format('Y-m-d'), 'formatted_date' => $item->departure_date->translatedFormat('F j, Y'), 'capacity' => $item->capacity]) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @php $availabilityStatus = $item->status->value ?? $item->status; @endphp
                                @php $capacitySnapshot = $capacitySnapshots->get($item->id); @endphp
                                <x-agency.status-badge :label="__('messages.'.$availabilityStatus)" :tone="$availabilityStatus === 'active' ? 'success' : ($availabilityStatus === 'cancelled' ? 'danger' : 'neutral')" />
                                <a href="{{ route('agency.availability.edit', ['availability' => $item->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.edit') }}</a>
                            </div>
                        </div>

                        <dl class="mt-5 grid gap-3 sm:grid-cols-4">
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs agency-text-subtle">{{ __('messages.reserved_travelers') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['reserved_travelers'] }}</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs agency-text-subtle">{{ __('messages.remaining_capacity') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['remaining_capacity'] }}</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs agency-text-subtle">{{ __('messages.occupancy') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['occupancy_percent'] }}%</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs agency-text-subtle">{{ __('messages.active_bookings') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['booking_count'] }}</dd></div>
                        </dl>
                        @if ($capacitySnapshot['sold_out'] || $capacitySnapshot['near_capacity'])
                            <p class="mt-4 inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold {{ $capacitySnapshot['sold_out'] ? 'agency-state-danger' : 'agency-state-warning' }}">
                                <span aria-hidden="true">{{ $capacitySnapshot['sold_out'] ? '!' : 'i' }}</span>
                                {{ $capacitySnapshot['sold_out'] ? __('messages.departure_sold_out') : __('messages.departure_near_capacity') }}
                            </p>
                        @endif

                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('agency.availability.status', ['availability' => $item->id, 'lang' => app()->getLocale()]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2 text-sm outline-none focus:border-[#e76f51]">
                                    <option value="active" {{ $availabilityStatus === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                                    <option value="inactive" {{ $availabilityStatus === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                                    <option value="cancelled" {{ $availabilityStatus === 'cancelled' ? 'selected' : '' }}>{{ __('messages.cancelled') }}</option>
                                </select>
                                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-3 py-2 text-sm font-semibold text-white hover:bg-[#102936]">{{ __('messages.update_status') }}</button>
                            </form>
                            <a href="{{ route('agency.bookings.index', ['availability_id' => $item->id, 'lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-sm font-semibold">{{ __('messages.view_related_bookings') }}</a>
                        </div>
                    </article>
                @empty
                    <div class="agency-empty-state rounded-2xl border border-dashed p-8 text-center agency-text-subtle">
                        {{ __('messages.no_departures') }}
                    </div>
                @endforelse
            </div>

            @if ($availability->hasPages())
                <div class="mt-6 flex justify-center">
                    {{ $availability->links() }}
                </div>
            @endif
        </div>
    </div>
</x-agency-shell>
