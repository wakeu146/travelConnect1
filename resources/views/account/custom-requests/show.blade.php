<x-account-shell :title="$travelRequest->title.' | TravelConnect'">
    <main data-custom-request-page class="mx-auto grid max-w-6xl gap-6 px-5 py-8 text-[#173042] sm:px-8 lg:px-10">
        <header class="grid gap-3">
            <a class="w-fit text-sm font-bold text-[#e76f51]" href="{{ route('account.custom-requests.index', ['lang' => app()->getLocale()]) }}">← {{ __('messages.custom_requests.my_requests') }}</a>
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#e7eceb] pb-5">
                <div class="grid gap-2">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-[#e76f51]">{{ __('messages.custom_requests.request_details') }} · {{ $travelRequest->reference }}</p>
                    <h1 class="font-serif text-3xl sm:text-4xl">{{ $travelRequest->title }}</h1>
                    <p class="text-sm text-[#607985]">{{ $travelRequest->origin }} → {{ $travelRequest->destination }}@if ($travelRequest->destination_country) · {{ $travelRequest->destination_country }}@endif</p>
                </div>
                <span class="border border-[#dce5e2] bg-[#f1f6f4] px-3 py-1.5 text-xs font-bold">{{ __('messages.custom_requests.statuses.'.$travelRequest->status) }}</span>
            </div>
        </header>
        @if (session('status'))<p role="status" class="border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>@endif
        @if ($errors->any())<div role="alert" class="grid gap-1 border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <section class="grid gap-5 border border-[#e7eceb] bg-white p-5 sm:p-6">
            <div class="grid gap-4 border-b border-[#e7eceb] pb-5 sm:grid-cols-2 lg:grid-cols-4">
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.origin') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->origin }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.destination') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->destination }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.destination_country') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->destination_country ?: __('messages.custom_requests.not_specified') }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.traveler_count') }}</p><p class="mt-1 font-semibold">{{ trans_choice('messages.custom_requests.travelers', $travelRequest->traveler_count, ['count' => $travelRequest->traveler_count]) }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.departure') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->travel_date?->format('M j, Y') ?? __('messages.custom_requests.not_specified') }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.return') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->return_date?->format('M j, Y') ?? __('messages.custom_requests.not_specified') }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.flexible_dates_status') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->flexible_dates ? __('messages.custom_requests.flexible_dates') : __('messages.custom_requests.fixed_dates') }}</p></div>
                <div><p class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.purpose') }}</p><p class="mt-1 font-semibold">{{ $travelRequest->purpose ?: __('messages.custom_requests.not_specified') }}</p></div>
            </div>
            <div class="grid gap-2">
                <h2 class="font-bold">{{ __('messages.custom_requests.description') }}</h2>
                <p class="whitespace-pre-line text-sm leading-6 text-[#607985]">{{ $travelRequest->description }}</p>
            </div>
            <div class="grid gap-2">
                <h2 class="font-bold">{{ __('messages.custom_requests.services') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($travelRequest->services as $service)
                        <span class="border border-[#dce5e2] bg-[#f7faf9] px-3 py-1.5 text-sm">{{ $service->name }}</span>
                    @endforeach
                </div>
            </div>
            <div class="grid gap-4 border-t border-[#e7eceb] pt-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <h2 class="font-bold">{{ __('messages.custom_requests.budget') }}</h2>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.minimum_budget') }}</dt><dd class="mt-1 font-semibold">{{ $travelRequest->budget_min !== null ? number_format((float) $travelRequest->budget_min, 2).' '.$travelRequest->budget_currency : __('messages.custom_requests.not_specified') }}</dd></div>
                        <div><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.custom_requests.maximum_budget') }}</dt><dd class="mt-1 font-semibold">{{ $travelRequest->budget_max !== null ? number_format((float) $travelRequest->budget_max, 2).' '.$travelRequest->budget_currency : __('messages.custom_requests.not_specified') }}</dd></div>
                    </dl>
                    <p class="text-sm text-[#607985]">{{ $travelRequest->budget_flexible ? __('messages.custom_requests.flexible_budget') : __('messages.custom_requests.fixed_budget') }}</p>
                </div>
                <div class="grid content-start gap-2">
                    <h2 class="font-bold">{{ __('messages.custom_requests.special_requirements') }}</h2>
                    <p class="whitespace-pre-line text-sm leading-6 text-[#607985]">{{ $travelRequest->special_requirements ?: __('messages.custom_requests.not_specified') }}</p>
                </div>
            </div>
            <div class="grid gap-2 border-t border-[#e7eceb] pt-5">
                <h2 class="font-bold">{{ __('messages.custom_requests.sharing') }}</h2>
                <p class="text-sm text-[#607985]">{{ $travelRequest->sharing_mode === 'all' ? __('messages.custom_requests.share_all') : __('messages.custom_requests.select_agencies') }} · {{ $travelRequest->recipients->count() }} {{ __('messages.custom_requests.agencies_contacted') }}</p>
                <ul class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ($travelRequest->recipients as $recipient)
                        <li class="border border-[#e7eceb] px-3 py-2">{{ $recipient->agency?->company_name }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="border-t border-[#e7eceb] pt-4 text-xs text-[#607985]">{{ __('messages.custom_requests.published') }} · {{ $travelRequest->published_at?->format('M j, Y H:i') ?? __('messages.custom_requests.not_specified') }}</p>
        </section>
        @if ($travelRequest->booking)
            @php
                $booking = $travelRequest->booking;
                $bookingStatus = $booking->status->value;
            @endphp
            <section class="grid gap-3 border border-emerald-200 bg-emerald-50 p-5">
                <h2 class="font-bold">{{ __('messages.custom_requests.booking_created') }}</h2>
                <p class="text-sm">{{ $booking->booking_reference }} · {{ __('messages.custom_requests.statuses.'.$bookingStatus) }} · {{ __('messages.custom_requests.payment_not_taken') }}</p>
                <button type="button" data-booking-details-open aria-haspopup="dialog" aria-controls="custom-request-booking-details" class="w-fit border border-[#173042] px-4 py-2.5 text-sm font-bold text-[#173042]">
                    {{ __('messages.custom_requests.view_booking') }}
                </button>
            </section>
            <dialog id="custom-request-booking-details" data-booking-details-dialog data-account-dialog data-booking-auto-open="{{ session('booking_created') ? 'true' : 'false' }}" class="w-full max-w-2xl overflow-y-auto border border-[#dce5e2] bg-white p-0 text-[#173042] shadow-2xl" aria-labelledby="custom-request-booking-title">
                <div class="grid gap-5 p-5 sm:p-7">
                    <header class="flex items-start justify-between gap-4 border-b border-[#e7eceb] pb-4">
                        <div class="grid gap-1">
                            <p class="text-xs font-bold uppercase tracking-[.14em] text-[#e76f51]">{{ __('messages.reference') }} · {{ $booking->booking_reference }}</p>
                            <h2 id="custom-request-booking-title" class="font-serif text-2xl">{{ $travelRequest->title }}</h2>
                            <p class="text-sm text-[#607985]">{{ $booking->agency?->company_name ?? __('messages.travel_agency') }}</p>
                        </div>
                        <button type="button" data-booking-details-close class="grid h-10 w-10 shrink-0 place-items-center border border-[#dce5e2] text-[#607985]" aria-label="{{ __('messages.close_booking_dialog') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </button>
                    </header>
                    @if (session('booking_created'))
                        <p role="status" class="border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
                            {{ __('messages.custom_requests.booking_creation_details', ['reference' => $booking->booking_reference]) }}
                        </p>
                    @endif
                    <span class="w-fit border border-[#dce5e2] bg-[#f1f6f4] px-3 py-1 text-xs font-bold uppercase tracking-[.12em]">{{ __('messages.booking_status_'.$bookingStatus) }}</span>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <div class="border border-[#e7eceb] p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.destination') }}</dt><dd class="mt-1 font-bold">{{ $travelRequest->destination }}</dd></div>
                        <div class="border border-[#e7eceb] p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.departure') }}</dt><dd class="mt-1 font-bold">{{ $travelRequest->travel_date?->translatedFormat('d M Y') ?? __('messages.not_available') }}</dd></div>
                        <div class="border border-[#e7eceb] p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.traveler_count_label') }}</dt><dd class="mt-1 font-bold">{{ $booking->traveler_count }}</dd></div>
                        <div class="border border-[#e7eceb] p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.total') }}</dt><dd class="mt-1 font-bold">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</dd></div>
                        <div class="border border-[#e7eceb] p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.payment') }}</dt><dd class="mt-1 font-bold">{{ __('messages.payment_status_'.$booking->payment_status->value) }}</dd></div>
                        <div class="border border-[#e7eceb] p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.booking_expiry_deadline') }}</dt><dd class="mt-1 font-bold">{{ $booking->expires_at ? $booking->expires_at->translatedFormat('d M Y, H:i').' '.$booking->expires_at->format('T') : __('messages.not_available') }}</dd></div>
                    </dl>
                    @if ($booking->notes)
                        <div class="border border-[#e7eceb] p-4"><h3 class="text-xs font-semibold text-[#607985]">{{ __('messages.notes') }}</h3><p class="mt-1 whitespace-pre-line text-sm">{{ $booking->notes }}</p></div>
                    @endif
                    @if (in_array($bookingStatus, ['pending', 'confirmed'], true))
                        <form action="{{ route('bookings.cancel', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" method="POST" class="flex justify-end border-t border-[#e7eceb] pt-4">
                            @csrf
                            <button type="submit" class="border border-[#dce5e2] px-4 py-2.5 text-sm font-semibold">{{ __('messages.cancel_booking') }}</button>
                        </form>
                    @endif
                </div>
            </dialog>
        @endif
        <section class="grid gap-4"><div><h2 class="font-serif text-3xl">{{ __('messages.custom_requests.agency_proposals') }}</h2><p class="mt-1 text-sm text-[#607985]">{{ $travelRequest->proposals->count() }} {{ __('messages.custom_requests.proposals_received') }}</p></div>
            @forelse ($travelRequest->proposals as $proposal)
                <article class="grid gap-4 border border-[#e7eceb] bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-lg font-bold">{{ $proposal->agency->company_name }}</h3><p class="text-xs text-[#607985]">{{ __('messages.custom_requests.verified_agency') }} · {{ $proposal->reference }} · {{ $proposal->submitted_at?->format('M j, Y') }}</p></div><div class="text-right"><p class="text-2xl font-extrabold">{{ number_format((float) $proposal->price, 2) }} {{ $proposal->currency }}</p><span class="text-xs font-semibold">{{ __('messages.custom_requests.statuses.'.$proposal->status) }}</span></div></div>
                    <div class="grid gap-3 text-sm sm:grid-cols-2"><div><h4 class="font-bold">{{ __('messages.custom_requests.services_included') }}</h4><p class="text-[#607985]">{{ collect($proposal->services_included)->join(', ') }}</p></div><div><h4 class="font-bold">{{ __('messages.custom_requests.services_excluded') }}</h4><p class="text-[#607985]">{{ collect($proposal->services_excluded)->join(', ') ?: __('messages.custom_requests.none_listed') }}</p></div><div class="sm:col-span-2"><h4 class="font-bold">{{ __('messages.custom_requests.agency_message') }}</h4><p class="whitespace-pre-line text-[#607985]">{{ $proposal->message }}</p></div>@if ($proposal->itinerary)<div class="sm:col-span-2"><h4 class="font-bold">{{ __('messages.custom_requests.itinerary') }}</h4><p class="whitespace-pre-line text-[#607985]">{{ $proposal->itinerary }}</p></div>@endif</div>
                    @if ($proposal->status === 'submitted' && $travelRequest->status === 'open')
                        <form method="POST" action="{{ route('account.custom-requests.proposals.select', ['travelRequest' => $travelRequest->id, 'proposal' => $proposal->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border-t border-[#e7eceb] pt-4 sm:grid-cols-[1fr_auto] sm:items-center">@csrf<label class="flex items-start gap-2 text-sm"><input required type="checkbox" name="confirm" value="1"><span>{{ __('messages.custom_requests.confirm_selection', ['agency' => $proposal->agency->company_name]) }} {{ __('messages.custom_requests.payment_not_taken') }}</span></label><button class="bg-[#e76f51] px-4 py-3 text-sm font-bold text-white">{{ __('messages.custom_requests.select_and_book') }}</button></form>
                    @endif
                </article>
            @empty
                <div class="border border-dashed border-[#cbd9d8] p-8 text-center text-sm text-[#607985]">{{ __('messages.custom_requests.no_proposals') }}</div>
            @endforelse
        </section>
        <section class="grid gap-3 border-t border-[#e7eceb] pt-6"><h2 class="font-serif text-2xl">{{ __('messages.custom_requests.history') }}</h2>@forelse ($travelRequest->events as $event)<p class="text-sm text-[#607985]">{{ $event->created_at?->format('M j, Y H:i') }} · {{ $event->actor?->name ?? __('messages.custom_requests.system') }} · {{ __('messages.custom_requests.events.'.$event->event_type, [], app()->getLocale()) }}</p>@empty<p class="text-sm text-[#607985]">{{ __('messages.custom_requests.no_history') }}</p>@endforelse</section>
    </main>
    <script>
        (() => {
            const requestPage = document.querySelector('[data-custom-request-page]');
            if (!requestPage) return;

            requestPage.querySelectorAll('[data-booking-details-open]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById(button.getAttribute('aria-controls'))?.showModal();
                });
            });

            requestPage.querySelectorAll('[data-booking-details-close]').forEach((button) => {
                button.addEventListener('click', () => button.closest('dialog')?.close());
            });

            requestPage.querySelectorAll('[data-booking-details-dialog]').forEach((dialog) => {
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) dialog.close();
                });

                if (dialog.dataset.bookingAutoOpen === 'true') dialog.showModal();
            });
        })();
    </script>
</x-account-shell>
