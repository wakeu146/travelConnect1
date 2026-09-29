<section class="mt-14 border-t border-[#e7eceb] pt-12" aria-labelledby="agency-packages-heading">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.travel_packages') }}</p>
            <h2 id="agency-packages-heading" class="mt-3 font-serif text-3xl tracking-tight sm:text-4xl">{{ __('messages.packages_from_agency') }}</h2>
        </div>
        @if ($packages->isNotEmpty())
            <span class="text-xs font-semibold uppercase tracking-[.14em] text-[#607985]">{{ $packages->count() }} {{ __('messages.travel_packages') }}</span>
        @endif
    </div>

    @if ($packages->isNotEmpty())
        <div class="mt-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($packages as $package)
                @php($coverImage = $package->images->firstWhere('is_primary', true)?->publicUrl() ?? $package->images->first()?->publicUrl())
                <article class="dashboard-panel group flex min-w-0 flex-col overflow-hidden border-[#e7eceb] bg-white transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="relative aspect-[16/10] overflow-hidden bg-[#173042]">
                        @if ($coverImage)
                            <img src="{{ $coverImage }}" alt="{{ $package->title }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        @else
                            <div class="flex h-full items-center justify-center text-white/75"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="h-10 w-10" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Zm0 9 8 4.5 8-4.5M4 7.5V16l8 4.5V12"/></svg></div>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <p class="text-xs font-bold uppercase tracking-[.14em] text-[#e76f51]">{{ $package->destination }}</p>
                        <h3 class="mt-2 text-lg font-bold">{{ $package->title }}</h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-[#607985]">{{ $package->summary }}</p>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[#e7eceb] pt-4 text-sm">
                            <span class="font-semibold text-[#607985]">{{ trans_choice('messages.duration_days_label', $package->duration_days, ['count' => $package->duration_days]) }}</span>
                            <span class="font-bold text-[#173042]">{{ __('messages.from_price_per_traveler', ['price' => number_format((float) $package->price_from, 2)]) }}</span>
                        </div>

                        @if (auth()->user()?->isTraveler() && $package->availabilities->isNotEmpty())
                            <button type="button" data-booking-modal-open aria-haspopup="dialog" aria-controls="package-booking-{{ $package->id }}" class="mt-5 inline-flex w-full items-center justify-center rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.book_this_package') }}</button>
                        @elseif (auth()->user()?->isTraveler())
                            <p class="mt-5 rounded-lg bg-[#f1f6f4] px-3 py-2.5 text-center text-xs font-semibold text-[#607985]">{{ __('messages.no_upcoming_departures_for_package') }}</p>
                        @endif
                    </div>
                </article>

                @if (auth()->user()?->isTraveler() && $package->availabilities->isNotEmpty())
                    <dialog id="package-booking-{{ $package->id }}" data-package-booking-dialog data-account-dialog data-booking-modal-reopen="{{ (string) old('booking_modal_package') === (string) $package->id ? 'true' : 'false' }}" class="w-full max-w-xl overflow-y-auto border p-0 shadow-2xl" aria-labelledby="package-booking-title-{{ $package->id }}">
                        <div class="p-5 sm:p-7">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.book_this_package') }}</p>
                                    <h2 id="package-booking-title-{{ $package->id }}" class="mt-2 font-serif text-2xl">{{ $package->title }}</h2>
                                    <p class="mt-1 text-sm text-[#607985]">{{ $package->destination }} · {{ trans_choice('messages.duration_days_label', $package->duration_days, ['count' => $package->duration_days]) }}</p>
                                </div>
                                <button type="button" data-booking-modal-close class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#607985] transition hover:bg-[#f1f6f4] hover:text-[#173042]" aria-label="{{ __('messages.close_booking_dialog') }}" title="{{ __('messages.close_booking_dialog') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                                </button>
                            </div>
                            <form method="POST" action="{{ route('bookings.store', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="mt-6 grid gap-4">
                                @csrf
                                <input type="hidden" name="booking_modal_package" value="{{ $package->id }}">
                                @if ($errors->any() && (string) old('booking_modal_package') === (string) $package->id)
                                    <div class="rounded-xl border border-[#fda29b] bg-[#fef3f2] p-3 text-sm font-semibold text-[#b42318]" role="alert">
                                        @foreach ($errors->all() as $error)
                                            <p>{{ $error }}</p>
                                        @endforeach
                                    </div>
                                @endif
                                <div>
                                    <label for="package-availability-{{ $package->id }}" class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.departure') }}</label>
                                    <select id="package-availability-{{ $package->id }}" name="package_availability_id" required class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                                        <option value="">{{ __('messages.select_departure') }}</option>
                                        @foreach ($package->availabilities as $availability)
                                            <option value="{{ $availability->id }}" @selected(old('package_availability_id') == $availability->id)>{{ $availability->departure_date->translatedFormat('d M Y') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="package-traveler-count-{{ $package->id }}" class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.traveler_count_label') }}</label>
                                    <input id="package-traveler-count-{{ $package->id }}" type="number" min="1" name="traveler_count" value="{{ old('traveler_count', 1) }}" required class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                                </div>
                                <div>
                                    <label for="package-notes-{{ $package->id }}" class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.notes') }} <span class="font-normal normal-case">({{ __('messages.optional') }})</span></label>
                                    <textarea id="package-notes-{{ $package->id }}" name="notes" rows="3" placeholder="{{ __('messages.optional_trip_details') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">{{ old('notes') }}</textarea>
                                </div>
                                <p class="rounded-xl bg-[#f1f6f4] px-4 py-3 text-sm font-semibold text-[#173042]">{{ __('messages.from_price_per_traveler', ['price' => number_format((float) $package->price_from, 2)]) }}</p>
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.request_booking') }}</button>
                            </form>
                        </div>
                    </dialog>
                @endif
            @endforeach
        </div>
    @else
        <p class="mt-6 border border-dashed border-[#cbd9d8] p-8 text-sm leading-7 text-[#607985]">{{ __('messages.no_published_packages') }}</p>
    @endif

    @if (session('booking_success'))
        @php($bookingSuccess = session('booking_success'))
        <dialog data-booking-success-dialog data-account-dialog class="w-full max-w-lg overflow-y-auto border p-0 shadow-2xl" aria-labelledby="booking-success-title">
            <div class="p-6 text-center sm:p-8">
                <span data-booking-success-icon class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#eaf6f1] text-[#247653]" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6"><path d="m5 12 4 4L19 6"/></svg>
                </span>
                <h2 id="booking-success-title" class="mt-4 font-serif text-2xl">{{ __('messages.booking_request_received') }}</h2>
                <p class="mt-2 text-sm leading-6 text-[#607985]">{{ __('messages.booking_confirmation_reference', ['package' => $bookingSuccess['package_title'], 'reference' => $bookingSuccess['booking_reference']]) }}</p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('account.bookings', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.my_bookings') }}</a>
                    <button type="button" data-booking-modal-close class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-3 text-sm font-bold text-[#607985] transition hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.continue_browsing') }}</button>
                </div>
            </div>
        </dialog>
    @endif
</section>

<script>
    (() => {
        const packageSection = document.querySelector('[aria-labelledby="agency-packages-heading"]');
        if (!packageSection) return;

        packageSection.querySelectorAll('[data-booking-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById(button.getAttribute('aria-controls'))?.showModal();
            });
        });

        packageSection.querySelectorAll('[data-booking-modal-close]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });

        packageSection.querySelectorAll('[data-account-dialog], [data-package-booking-dialog]').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        });

        packageSection.querySelectorAll('[data-package-booking-dialog]').forEach((dialog) => {
            if (dialog.dataset.bookingModalReopen === 'true') dialog.showModal();
        });

        packageSection.querySelector('[data-booking-success-dialog]')?.showModal();
    })();
</script>
