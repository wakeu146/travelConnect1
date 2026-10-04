<x-account-shell :title="__('messages.booking').' | TravelConnect'">
    <main data-bookings-page class="mx-auto max-w-7xl px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <header class="border-b border-[#e4e9e7] pb-7">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.workspace') }}</p>
            <h1 class="mt-2 font-serif text-4xl tracking-tight">{{ __('messages.booking') }}</h1>
            <p class="mt-3 text-sm text-[#607985]">{{ __('messages.my_bookings_intro') }}</p>
        </header>

        <section class="mt-8 border border-[#e7eceb] bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.booking') }}</p>
                    <h2 class="mt-2 text-2xl font-bold">{{ __('messages.book_this_package') }}</h2>
                </div>
                <a href="{{ route('account.discover', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.discover_agencies') }}</a>
            </div>

            <form method="GET" action="{{ route('account.bookings', ['lang' => app()->getLocale()]) }}" class="mt-6 grid gap-4">
                <label class="block">
                    <span class="block text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.destination_country') }}</span>
                    <select name="country_id" class="mt-2 w-full border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" onchange="this.form.submit()">
                        <option value="">{{ __('messages.select_destination_country') }}</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) ($selectedCountryId ?? '') === (string) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </label>
            </form>

            @if ($selectedCountry)
                <section class="mt-6" aria-labelledby="country-packages-title">
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.15em] text-[#e76f51]">{{ __('messages.destination_country') }}</p>
                            <h3 id="country-packages-title" class="mt-2 text-xl font-bold">{{ __('messages.available_packages_in_country', ['country' => $selectedCountry->name]) }}</h3>
                        </div>
                        <span class="text-xs font-semibold text-[#607985]">{{ $bookableAgencies->sum(fn ($agency) => $agency->packages->count()) }} {{ __('messages.travel_packages') }}</span>
                    </div>

                    @if ($bookableAgencies->isNotEmpty())
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($bookableAgencies as $agency)
                                @foreach ($agency->packages as $package)
                                    @php
                                        $coverImage = $package->images->firstWhere('is_primary', true)?->publicUrl() ?? $package->images->first()?->publicUrl();
                                    @endphp
                                    <article class="overflow-hidden border border-[#e7eceb] bg-[#f1f6f4]">
                                        @if ($coverImage)
                                            <img src="{{ $coverImage }}" alt="{{ $package->title }}" class="aspect-[16/9] w-full object-cover">
                                        @else
                                            <div class="flex aspect-[16/9] items-center justify-center bg-[#173042] text-white/75">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="h-10 w-10" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Zm0 9 8 4.5 8-4.5M4 7.5V16l8 4.5V12"/></svg>
                                            </div>
                                        @endif
                                        <div class="p-4">
                                            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#e76f51]">{{ $agency->company_name }}</p>
                                            <h4 class="mt-2 text-lg font-bold">{{ $package->title }}</h4>
                                            <p class="mt-2 text-sm text-[#607985]">{{ $package->destination }} · {{ trans_choice('messages.duration_days_label', $package->duration_days, ['count' => $package->duration_days]) }}</p>
                                            <p class="mt-2 text-sm text-[#607985]">{{ __('messages.departure') }}: {{ $package->availabilities->first()->departure_date->translatedFormat('d M Y') }}</p>
                                            <p class="mt-2 text-sm font-bold">{{ __('messages.from_price_per_traveler', ['price' => number_format((float) $package->price_from, 2)]) }}</p>
                                            <a href="{{ route('account.bookings', ['lang' => app()->getLocale(), 'country_id' => $selectedCountry->id, 'agency_id' => $agency->id, 'package_id' => $package->id]) }}" class="mt-4 inline-flex w-full items-center justify-center bg-[#e76f51] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.select_package') }}</a>
                                        </div>
                                    </article>
                                @endforeach
                            @endforeach
                        </div>
                    @else
                        <p class="mt-4 border border-dashed border-[#cbd9d8] p-5 text-sm text-[#607985]">{{ __('messages.no_available_packages_for_country', ['country' => $selectedCountry->name]) }}</p>
                    @endif
                </section>
            @endif

            @if ($selectedAgency && $selectedPackage)
                @php
                    $primaryImage = $selectedPackage->images->firstWhere('is_primary', true)?->publicUrl() ?? $selectedPackage->images->first()?->publicUrl();
                    $activeAvailabilities = $selectedPackage->availabilities->filter(fn ($availability) => $availability->status->value === 'active');
                @endphp

                <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
                    <div class="overflow-hidden border border-[#e7eceb] bg-[#f1f6f4]">
                        @if ($primaryImage)
                            <img src="{{ $primaryImage }}" alt="{{ $selectedPackage->title }}" class="h-56 w-full object-cover">
                        @else
                            <div class="flex h-56 items-center justify-center bg-[#173042] text-white/75">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="h-12 w-12" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Zm0 9 8 4.5 8-4.5M4 7.5V16l8 4.5V12"/></svg>
                            </div>
                        @endif
                        <div class="p-5">
                            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#e76f51]">{{ $selectedAgency->company_name }}</p>
                            @if (filled($selectedAgency->address))<p class="mt-1 text-sm text-[#607985]">{{ __('messages.agency_location') }}: {{ $selectedAgency->address }}</p>@endif
                            <h3 class="mt-2 text-2xl font-bold">{{ $selectedPackage->title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-[#607985]">{{ $selectedPackage->summary }}</p>
                            <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-[#607985]">
                                <span>{{ $selectedPackage->destination }}</span>
                                <span>•</span>
                                <span>{{ trans_choice('messages.duration_days_label', $selectedPackage->duration_days, ['count' => $selectedPackage->duration_days]) }}</span>
                                <span>•</span>
                                <span>{{ __('messages.from_price_per_traveler', ['price' => number_format((float) $selectedPackage->price_from, 2)]) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border border-[#e7eceb] bg-[#f1f6f4] p-5">
                        <p class="text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.payment') }}</p>
                        <p class="mt-3 text-3xl font-bold">{{ __('messages.from_price_per_traveler', ['price' => number_format((float) $selectedPackage->price_from, 2)]) }}</p>
                        @if ($activeAvailabilities->isNotEmpty())
                            <p class="mt-2 text-sm text-[#607985]">{{ $activeAvailabilities->count() }} {{ __('messages.availability') }} {{ strtolower(__('messages.available')) }}</p>
                        @endif
                        <form method="POST" action="{{ route('account.bookings.store', ['lang' => app()->getLocale()]) }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="country_id" value="{{ $selectedCountry->id }}">
                            <input type="hidden" name="agency_id" value="{{ $selectedAgency->id }}">
                            <input type="hidden" name="package_id" value="{{ $selectedPackage->id }}">

                            <div>
                                <label for="package_availability_id" class="block text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.departure') }}</label>
                                <select id="package_availability_id" name="package_availability_id" required class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-white px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                                    <option value="">{{ __('messages.select_departure') }}</option>
                                    @foreach ($activeAvailabilities as $availability)
                                        <option value="{{ $availability->id }}" @selected(old('package_availability_id') == $availability->id)>{{ $availability->departure_date->translatedFormat('d M Y') }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="traveler_count" class="block text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.traveler_count_label') }}</label>
                                <input id="traveler_count" type="number" min="1" name="traveler_count" value="{{ old('traveler_count', 1) }}" required class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-white px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>

                            <div>
                                <label for="notes" class="block text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.notes') }} <span class="font-normal normal-case text-[#607985]">({{ __('messages.optional') }})</span></label>
                                <textarea id="notes" name="notes" rows="3" placeholder="{{ __('messages.optional_trip_details') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-white px-4 py-3 text-sm outline-none focus:border-[#e76f51]">{{ old('notes') }}</textarea>
                            </div>

                            @if ($errors->any())
                                <div class="rounded-xl border border-[#fda29b] bg-[#fef3f2] p-3 text-sm text-[#b42318]" role="alert">
                                    @foreach ($errors->all() as $error)
                                        <p>{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.request_booking') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </section>

        <section class="mt-10">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-2xl font-bold">{{ __('messages.my_bookings') }}</h2>
                @if ($bookings->count() > 0)
                    <span class="text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ $bookings->count() }}</span>
                @endif
            </div>

            <div class="grid gap-4">
                @forelse ($bookings as $booking)
                    @php
                        $bookingStatus = $booking->status->value;
                        $statusClasses = match ($bookingStatus) {
                            'confirmed', 'completed' => 'bg-[#eaf6f1] text-[#247653]',
                            'pending' => 'bg-[#fff5df] text-[#9a6b12]',
                            default => 'bg-[#f1f6f4] text-[#607985]',
                        };
                    @endphp
                    <article class="dashboard-panel border-[#e7eceb] bg-white p-5 sm:p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-bold">{{ $booking->package?->title ?? __('messages.travel_package') }}</h3>
                                    <span data-booking-status="{{ $bookingStatus }}" class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] {{ $statusClasses }}">{{ __('messages.booking_status_'.$bookingStatus) }}</span>
                                </div>
                                <p class="mt-2 text-sm text-[#607985]">{{ $booking->agency?->company_name ?? __('messages.travel_agency') }}</p>
                            </div>
                            <p class="shrink-0 text-xs font-bold uppercase tracking-[.12em] text-[#607985]">{{ __('messages.reference') }}: {{ $booking->booking_reference }}</p>
                        </div>

                        <dl class="mt-5 grid gap-4 border-t border-[#e7eceb] pt-5 sm:grid-cols-2 lg:grid-cols-4">
                            <div><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.departure') }}</dt><dd class="mt-1 text-sm font-bold">{{ $booking->availability?->departure_date?->translatedFormat('d M Y') ?? __('messages.not_available') }}</dd></div>
                            <div><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.traveler_count_label') }}</dt><dd class="mt-1 text-sm font-bold">{{ $booking->traveler_count }}</dd></div>
                            <div><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.total') }}</dt><dd class="mt-1 text-sm font-bold">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</dd></div>
                            <div><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.payment') }}</dt><dd class="mt-1 text-sm font-bold">{{ __('messages.payment_status_'.$booking->payment_status->value) }}</dd></div>
                        </dl>

                        <div class="mt-5 flex justify-end">
                            <button type="button" data-booking-details-open aria-haspopup="dialog" aria-controls="booking-details-{{ $booking->id }}" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.view_booking') }}</button>
                        </div>
                    </article>

                    <dialog id="booking-details-{{ $booking->id }}" data-booking-details-dialog data-account-dialog class="w-full max-w-2xl overflow-y-auto border p-0 shadow-2xl" aria-labelledby="booking-details-title-{{ $booking->id }}">
                        <div class="p-5 sm:p-7">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.reference') }} · {{ $booking->booking_reference }}</p>
                                    <h2 id="booking-details-title-{{ $booking->id }}" class="mt-2 font-serif text-2xl">{{ $booking->package?->title ?? __('messages.travel_package') }}</h2>
                                    <p class="mt-1 text-sm text-[#607985]">{{ $booking->agency?->company_name ?? __('messages.travel_agency') }}</p>
                                </div>
                                <button type="button" data-booking-details-close class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#607985] transition hover:bg-[#f1f6f4] hover:text-[#173042]" aria-label="{{ __('messages.close_booking_dialog') }}" title="{{ __('messages.close_booking_dialog') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                                </button>
                            </div>
                            <span data-booking-status="{{ $bookingStatus }}" class="mt-4 inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] {{ $statusClasses }}">{{ __('messages.booking_status_'.$bookingStatus) }}</span>
                            <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div data-booking-detail-surface class="border p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.departure') }}</dt><dd class="mt-1 font-bold">{{ $booking->availability?->departure_date?->translatedFormat('d M Y') ?? __('messages.not_available') }}</dd></div>
                                <div data-booking-detail-surface class="border p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.traveler_count_label') }}</dt><dd class="mt-1 font-bold">{{ $booking->traveler_count }}</dd></div>
                                <div data-booking-detail-surface class="border p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.total') }}</dt><dd class="mt-1 font-bold">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</dd></div>
                                <div data-booking-detail-surface class="border p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.payment') }}</dt><dd class="mt-1 font-bold">{{ __('messages.payment_status_'.$booking->payment_status->value) }}</dd></div>
                            </dl>
                            @if ($booking->notes)
                                <div data-booking-detail-surface class="mt-3 border p-4"><p class="text-xs font-semibold text-[#607985]">{{ __('messages.notes') }}</p><p class="mt-1 text-sm">{{ $booking->notes }}</p></div>
                            @endif
                            @if (in_array($bookingStatus, ['pending', 'confirmed'], true))
                                <form action="{{ route('bookings.cancel', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" method="POST" class="mt-5 flex justify-end">
                                    @csrf
                                    <button type="submit" data-booking-cancel class="inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold">{{ __('messages.cancel_booking') }}</button>
                                </form>
                            @endif
                        </div>
                    </dialog>
                @empty
                    <div class="dashboard-panel border-dashed border-[#cbd9d8] bg-transparent p-8 text-center sm:p-12">
                        <h3 class="text-lg font-bold">{{ __('messages.no_bookings') }}</h3>
                        <p class="mt-2 text-sm text-[#607985]">{{ __('messages.no_bookings_hint') }}</p>
                    </div>
                @endforelse
            </div>
            @if ($bookings->hasPages())
                <div class="mt-6">{{ $bookings->links() }}</div>
            @endif
        </section>
    </main>
    <script>
        (() => {
            const bookingsPage = document.querySelector('[data-bookings-page]');
            if (!bookingsPage) return;

            bookingsPage.querySelectorAll('[data-booking-details-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById(button.getAttribute('aria-controls'))?.showModal();
                });
            });

            bookingsPage.querySelectorAll('[data-booking-details-close]').forEach((button) => {
                button.addEventListener('click', () => button.closest('dialog')?.close());
            });

            bookingsPage.querySelectorAll('[data-booking-details-dialog]').forEach((dialog) => {
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) dialog.close();
                });
            });
        })();
    </script>
</x-account-shell>
