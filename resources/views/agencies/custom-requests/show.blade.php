<x-agency-shell :title="$travelRequest->title.' | TravelConnect'">
    <main data-custom-request-page class="mx-auto grid max-w-5xl gap-6 px-5 py-8 text-[#173042] sm:px-8 lg:px-10">
        <header class="grid gap-3">
            <a class="w-fit text-sm font-bold text-[#e76f51]" href="{{ route('agency.custom-requests.index', ['lang' => app()->getLocale()]) }}">← {{ __('messages.custom_requests.inbox') }}</a>
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#e7eceb] pb-5">
                <div class="grid gap-2">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-[#e76f51]">{{ $travelRequest->reference }}</p>
                    <h1 class="font-serif text-3xl sm:text-4xl">{{ $travelRequest->title }}</h1>
                    <p class="text-sm text-[#607985]">{{ $travelRequest->origin }} → {{ $travelRequest->destination }}@if ($travelRequest->destination_country) · {{ $travelRequest->destination_country }}@endif</p>
                </div>
                <span class="border border-[#dce5e2] bg-[#f1f6f4] px-3 py-1.5 text-xs font-bold">{{ __('messages.custom_requests.statuses.'.$travelRequest->status) }}</span>
            </div>
        </header>
        @if (session('status'))<p role="status" class="border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>@endif
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
                <p class="text-sm text-[#607985]">{{ $travelRequest->sharing_mode === 'all' ? __('messages.custom_requests.share_all') : __('messages.custom_requests.select_agencies') }} · {{ $travelRequest->recipients_count }} {{ __('messages.custom_requests.agencies_contacted') }}</p>
            </div>
            <p class="border-t border-[#e7eceb] pt-4 text-xs text-[#607985]">{{ __('messages.custom_requests.published') }} · {{ $travelRequest->published_at?->format('M j, Y H:i') ?? __('messages.custom_requests.not_specified') }}</p>
        </section>
        <div class="flex flex-wrap gap-3">
            @if ($recipient->status === 'active' && ! $recipient->read_at)
                <form method="POST" action="{{ route('agency.custom-requests.read', ['travelRequest' => $travelRequest->id, 'lang' => app()->getLocale()]) }}">@csrf @method('PATCH')<button class="border border-[#dce5e2] px-4 py-3 text-sm font-bold">{{ __('messages.custom_requests.mark_read') }}</button></form>
            @endif
            @if ($travelRequest->status === 'open' && $recipient->status === 'active' && (! $proposal || $proposal->status === 'withdrawn'))
                <a href="{{ route('agency.custom-requests.proposal.create', ['travelRequest' => $travelRequest->id, 'lang' => app()->getLocale()]) }}" class="bg-[#e76f51] px-4 py-3 text-sm font-bold text-white">{{ __('messages.custom_requests.submit_proposal') }}</a>
            @endif
            @if ($travelRequest->booking)
                <a href="{{ route('agency.bookings.show', ['booking' => $travelRequest->booking->id, 'lang' => app()->getLocale()]) }}" class="border border-[#dce5e2] px-4 py-3 text-sm font-bold">{{ __('messages.custom_requests.view_booking') }}</a>
            @endif
        </div>
        @if ($proposal)<section class="grid gap-3 border border-[#e7eceb] bg-white p-5"><h2 class="font-bold">{{ __('messages.custom_requests.your_proposal') }}</h2><p class="text-sm">{{ number_format((float) $proposal->price, 2) }} {{ $proposal->currency }} · {{ __('messages.custom_requests.statuses.'.$proposal->status) }}</p><p class="whitespace-pre-line text-sm text-[#607985]">{{ $proposal->message }}</p>@if ($proposal->status === 'submitted')<form method="POST" action="{{ route('agency.custom-request-proposals.withdraw', ['proposal' => $proposal->id, 'lang' => app()->getLocale()]) }}">@csrf @method('PATCH')<button class="text-sm font-bold text-rose-700 underline">{{ __('messages.custom_requests.withdraw_proposal') }}</button></form>@endif</section>@endif
    </main>
</x-agency-shell>
