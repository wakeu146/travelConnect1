<x-agency-shell>
    <div class="mx-auto max-w-6xl">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.travel_packages') }}</p>
                <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.manage_travel_packages') }}</h1>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.back_to_dashboard') }}</a>
                <a href="{{ route('agency.packages.create', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.create_package') }}</a>
            </div>
        </div>

            @if (session('status'))
                <div class="agency-panel mb-6 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="agency-panel mb-6 rounded-2xl border p-4 shadow-sm">
                <form method="GET" action="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="flex-1">
                        <label for="package-search" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.search_packages') }}</label>
                        <input id="package-search" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('messages.search_packages') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                    </div>
                    <div class="lg:w-56">
                        <label for="package-status" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.status') }}</label>
                        <select id="package-status" name="status" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            <option value="">{{ __('messages.all_statuses') }}</option>
                            <option value="draft" {{ ($status ?? '') === 'draft' ? 'selected' : '' }}>{{ __('messages.draft') }}</option>
                            <option value="published" {{ ($status ?? '') === 'published' ? 'selected' : '' }}>{{ __('messages.published') }}</option>
                            <option value="archived" {{ ($status ?? '') === 'archived' ? 'selected' : '' }}>{{ __('messages.archived') }}</option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.search') }}</button>
                        @if (($search ?? '') !== '' || ($status ?? '') !== '')
                            <a href="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-3 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.clear_filters') }}</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                @forelse ($packages as $package)
                    @php
                        $formattedPrice = number_format((float) $package->price_from, 2);
                        $packageStatus = $package->status->value ?? $package->status;
                        $readinessChecks = [
                            $package->images_count > 0,
                            $package->itinerary_days_count >= $package->duration_days,
                        ];
                        $readinessPercent = (int) round((count(array_filter($readinessChecks)) / count($readinessChecks)) * 100);
                    @endphp
                    <article class="agency-panel rounded-2xl border p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ $package->destination }}</p>
                                <h2 class="mt-2 text-2xl font-bold">{{ $package->title }}</h2>
                                <p class="mt-2 text-sm text-[#607985]">{{ $package->summary }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-agency.status-badge :label="__('messages.'.$packageStatus)" :tone="$packageStatus === 'published' ? 'success' : ($packageStatus === 'archived' ? 'neutral' : 'warning')" />
                                <a href="{{ route('agency.packages.edit', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.edit') }}</a>
                            </div>
                        </div>
                        <div class="mt-5 flex flex-wrap gap-3 text-sm text-[#607985]">
                            <span>{{ __('messages.duration_days_label', ['count' => $package->duration_days]) }}</span>
                            <span>•</span>
                            <span>{{ __('messages.from_price_amount', ['amount' => $formattedPrice]) }}</span>
                        </div>
                        <div class="mt-5 grid gap-4 border-t agency-divider pt-4 sm:grid-cols-[minmax(180px,.7fr)_1.3fr]">
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div><p class="agency-text-subtle">{{ __('messages.upcoming_departures') }}</p><p class="agency-text-primary mt-1 font-bold tabular-nums">{{ $package->upcoming_departures_count }}</p></div>
                                <div><p class="agency-text-subtle">{{ __('messages.bookings') }}</p><p class="agency-text-primary mt-1 font-bold tabular-nums">{{ $package->bookings_count }}</p></div>
                                <div><p class="agency-text-subtle">{{ __('messages.images') }}</p><p class="agency-text-primary mt-1 font-bold tabular-nums">{{ $package->images_count }}</p></div>
                                <div><p class="agency-text-subtle">{{ __('messages.itinerary_days') }}</p><p class="agency-text-primary mt-1 font-bold tabular-nums">{{ $package->itinerary_days_count }}/{{ $package->duration_days }}</p></div>
                            </div>
                            <div>
                                <x-agency.progress :value="$readinessPercent" :label="__('messages.package_readiness')" />
                                <p class="agency-text-subtle mt-2 text-xs">{{ $readinessPercent === 100 ? __('messages.package_ready') : __('messages.package_needs_work') }}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('agency.availability.index', ['package_id' => $package->id, 'lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-semibold">{{ __('messages.manage_departures') }}</a>
                            <span class="agency-text-subtle self-center text-xs">{{ __('messages.package_traveler_count', ['count' => (int) $package->travelers_booked]) }}</span>
                        </div>
                    </article>
                @empty
                    <div class="agency-empty-state rounded-2xl border border-dashed p-8 text-center agency-text-subtle">
                        {{ __('messages.no_packages_yet') }}
                    </div>
                @endforelse
            </div>

        @if ($packages->hasPages())
            <div class="mt-6 flex justify-center">
                {{ $packages->links() }}
            </div>
        @endif
    </div>
</x-agency-shell>
