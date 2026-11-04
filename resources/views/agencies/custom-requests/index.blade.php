<x-agency-shell :title="__('messages.custom_requests.inbox').' | TravelConnect'">
    <main data-custom-request-page class="mx-auto grid max-w-7xl gap-6 px-5 py-8 text-[#173042] sm:px-8 lg:px-10">
        <header class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.agency_workspace') }}</p><h1 class="mt-2 font-serif text-4xl">{{ __('messages.custom_requests.inbox') }}</h1><p class="mt-2 text-sm text-[#607985]">{{ __('messages.custom_requests.agency_intro') }}</p></div><a href="{{ route('agency.custom-requests.applications', ['lang' => app()->getLocale()]) }}" class="rounded-xl border px-4 py-3 text-sm font-bold">{{ __('messages.custom_requests.my_applications') }}</a></header>
        @if (session('status'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>@endif
        <form method="GET" class="grid gap-3 rounded-2xl border border-[#e7eceb] bg-white p-4 sm:grid-cols-2 lg:grid-cols-4">
            <label class="grid gap-1 text-xs font-semibold">{{ __('messages.custom_requests.search') }}<input name="search" value="{{ $filters['search'] ?? '' }}" class="rounded-lg border border-[#dce5e2] p-2.5 text-sm"></label>
            <label class="grid gap-1 text-xs font-semibold">{{ __('messages.custom_requests.read_status') }}<select name="read" class="rounded-lg border border-[#dce5e2] p-2.5 text-sm"><option value="">{{ __('messages.custom_requests.all') }}</option><option value="unread" @selected(($filters['read'] ?? '') === 'unread')>{{ __('messages.custom_requests.unread') }}</option><option value="read" @selected(($filters['read'] ?? '') === 'read')>{{ __('messages.custom_requests.read') }}</option></select></label>
            <label class="grid gap-1 text-xs font-semibold">{{ __('messages.custom_requests.application_status') }}<select name="applied" class="rounded-lg border border-[#dce5e2] p-2.5 text-sm"><option value="">{{ __('messages.custom_requests.all') }}</option><option value="yes" @selected(($filters['applied'] ?? '') === 'yes')>{{ __('messages.custom_requests.applied') }}</option><option value="no" @selected(($filters['applied'] ?? '') === 'no')>{{ __('messages.custom_requests.not_applied') }}</option></select></label>
            <label class="grid gap-1 text-xs font-semibold">{{ __('messages.custom_requests.destination') }}<input name="destination" value="{{ $filters['destination'] ?? '' }}" class="rounded-lg border border-[#dce5e2] p-2.5 text-sm"></label>
            <button class="rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-bold text-white lg:col-span-4">{{ __('messages.custom_requests.apply_filters') }}</button>
        </form>
        <section class="grid gap-4">
            @forelse ($requests as $travelRequest)
                @php($recipient = $travelRequest->recipients->first())
                @php($proposal = $travelRequest->agency_proposals_count > 0)
                <article class="grid gap-4 rounded-2xl border border-[#e7eceb] bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center">
                    <div class="grid gap-2"><div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold text-[#e76f51]">{{ $travelRequest->reference }}</span><span class="rounded-full bg-[#f1f6f4] px-3 py-1 text-xs font-semibold">{{ __('messages.custom_requests.statuses.'.$travelRequest->status) }}</span><span class="text-xs text-[#607985]">{{ $recipient?->read_at ? __('messages.custom_requests.read') : __('messages.custom_requests.unread') }}</span>@if ($proposal)<span class="text-xs text-[#607985]">{{ __('messages.custom_requests.applied') }}</span>@endif</div><h2 class="text-lg font-bold">{{ $travelRequest->title }}</h2><p class="text-sm text-[#607985]">{{ $travelRequest->origin }} → {{ $travelRequest->destination }} · {{ $travelRequest->travel_date?->format('M j, Y') }} · {{ trans_choice('messages.custom_requests.travelers', $travelRequest->traveler_count, ['count' => $travelRequest->traveler_count]) }}</p><p class="text-xs text-[#607985]">{{ $travelRequest->services->pluck('name')->join(', ') }} · {{ __('messages.custom_requests.budget') }}: {{ $travelRequest->budget_max ? number_format((float) $travelRequest->budget_max, 2).' '.$travelRequest->budget_currency : __('messages.custom_requests.not_specified') }}</p></div>
                    <a href="{{ route('agency.custom-requests.show', ['travelRequest' => $travelRequest->id, 'lang' => app()->getLocale()]) }}" class="inline-flex justify-center rounded-lg border border-[#dce5e2] px-4 py-2 text-sm font-bold">{{ __('messages.custom_requests.view_details') }}</a>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[#cbd9d8] p-10 text-center"><h2 class="font-bold">{{ __('messages.custom_requests.inbox_empty') }}</h2><p class="mt-2 text-sm text-[#607985]">{{ __('messages.custom_requests.inbox_empty_hint') }}</p></div>
            @endforelse
        </section>
        {{ $requests->links() }}
    </main>
</x-agency-shell>
