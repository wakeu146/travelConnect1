<x-agency-shell>
    <div class="mx-auto max-w-6xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.bookings') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.booking_management') }}</h1>
                </div>
                <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.back_to_dashboard') }}</a>
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-agency.metric :label="__('messages.bookings')" :value="$bookings->total()" />
                <x-agency.metric :label="__('messages.pending_bookings')" :value="(int) ($statusCounts['pending'] ?? 0)" tone="warning" />
                <x-agency.metric :label="__('messages.confirmed_bookings')" :value="(int) ($statusCounts['confirmed'] ?? 0)" tone="success" />
                <x-agency.metric :label="__('messages.completed_bookings')" :value="(int) ($statusCounts['completed'] ?? 0)" />
            </div>

            <div class="agency-panel mb-6 rounded-2xl border p-4 shadow-sm">
                <form method="GET" action="{{ route('agency.bookings.index', ['lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <label for="booking-search" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.search') }}</label>
                        <input id="booking-search" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('messages.reference_traveler_package') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                    </div>
                    <div class="lg:w-52">
                        <label for="booking-status" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.status') }}</label>
                        <select id="booking-status" name="status" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            <option value="">{{ __('messages.all') }}</option>
                            <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>{{ __('messages.booking_status_pending') }}</option>
                            <option value="confirmed" {{ ($status ?? '') === 'confirmed' ? 'selected' : '' }}>{{ __('messages.booking_status_confirmed') }}</option>
                            <option value="rejected" {{ ($status ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('messages.booking_status_rejected') }}</option>
                            <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>{{ __('messages.booking_status_cancelled') }}</option>
                            <option value="completed" {{ ($status ?? '') === 'completed' ? 'selected' : '' }}>{{ __('messages.booking_status_completed') }}</option>
                        </select>
                    </div>
                    <div class="lg:w-52">
                        <label for="booking-date" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.date') }}</label>
                        <input id="booking-date" type="date" name="date" value="{{ $date ?? '' }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                    </div>
                    <div class="lg:w-56">
                        <label for="booking-package" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.package') }}</label>
                        <select id="booking-package" name="package_id" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            <option value="">{{ __('messages.all_packages') }}</option>
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" @selected((string) ($packageId ?? '') === (string) $package->id)>{{ $package->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lg:w-56">
                        <label for="booking-sort" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.sort_by') }}</label>
                        <select id="booking-sort" name="sort" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            @foreach (['newest', 'oldest', 'amount_high', 'amount_low', 'travelers_high'] as $sortOption)
                                <option value="{{ $sortOption }}" @selected(($sort ?? 'newest') === $sortOption)>{{ __('messages.sort_'.$sortOption) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.search') }}</button>
                        @if (($search ?? '') !== '' || ($status ?? '') !== '' || ($date ?? '') !== '' || ($packageId ?? '') !== '')
                            <a href="{{ route('agency.bookings.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-3 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.clear_filters') }}</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                @forelse ($bookings as $booking)
                    <article class="agency-panel rounded-2xl border p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ $booking->booking_reference }}</p>
                                <h2 class="mt-2 text-2xl font-bold">{{ $booking->package?->title ?? __('messages.travel_package') }}</h2>
                                <p class="mt-2 text-sm text-[#607985]">{{ __('messages.traveler') }}: {{ $booking->traveler?->name ?? __('messages.guest') }} · {{ __('messages.travelers_count', ['count' => $booking->traveler_count]) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @php $bookingStatus = $booking->status->value ?? $booking->status; @endphp
                                <x-agency.status-badge :label="__('messages.booking_status_'.$bookingStatus)" :tone="$bookingStatus === 'confirmed' || $bookingStatus === 'completed' ? 'success' : (in_array($bookingStatus, ['rejected', 'cancelled', 'expired'], true) ? 'danger' : 'warning')" />
                                <a href="{{ route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.view_booking') }}</a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="agency-empty-state rounded-2xl border border-dashed p-8 text-center agency-text-subtle">
                        {{ __('messages.no_bookings') }}
                    </div>
                @endforelse
            </div>

            @if ($bookings->hasPages())
                <div class="mt-6 flex justify-center">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>
    </div>
</x-agency-shell>
