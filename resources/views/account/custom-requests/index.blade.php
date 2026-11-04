<x-account-shell :title="__('messages.custom_requests.my_requests').' | TravelConnect'">
    <main data-custom-request-page class="mx-auto grid max-w-6xl gap-6 px-5 py-8 text-[#173042] sm:px-8 lg:px-10">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.traveler_workspace') }}</p>
                <h1 class="mt-2 font-serif text-4xl">{{ __('messages.custom_requests.my_requests') }}</h1>
                <p class="mt-2 text-sm text-[#607985]">{{ __('messages.custom_requests.traveler_intro') }}</p>
            </div>
            <a href="{{ route('account.custom-requests.create', ['lang' => app()->getLocale()]) }}" class="rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white">{{ __('messages.custom_requests.create') }}</a>
        </header>
        @if (session('status'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>@endif
        <section class="grid gap-4">
            @forelse ($requests as $travelRequest)
                <article class="grid gap-4 rounded-2xl border border-[#e7eceb] bg-white p-5 shadow-sm sm:grid-cols-[1fr_auto] sm:items-center">
                    <div class="grid gap-2">
                        <div class="flex flex-wrap items-center gap-2"><span class="text-xs font-bold text-[#e76f51]">{{ $travelRequest->reference }}</span><span class="rounded-full bg-[#f1f6f4] px-3 py-1 text-xs font-semibold">{{ __('messages.custom_requests.statuses.'.$travelRequest->status) }}</span></div>
                        <h2 class="text-lg font-bold">{{ $travelRequest->title }}</h2>
                        <p class="text-sm text-[#607985]">{{ $travelRequest->origin }} → {{ $travelRequest->destination }} · {{ $travelRequest->travel_date?->format('M j, Y') }} · {{ trans_choice('messages.custom_requests.travelers', $travelRequest->traveler_count, ['count' => $travelRequest->traveler_count]) }}</p>
                        <p class="text-xs text-[#607985]">{{ $travelRequest->recipients_count }} {{ __('messages.custom_requests.agencies_contacted') }} · {{ $travelRequest->proposals_count }} {{ __('messages.custom_requests.proposal_count') }}</p>
                    </div>
                    <a href="{{ route('account.custom-requests.show', ['travelRequest' => $travelRequest->id, 'lang' => app()->getLocale()]) }}" class="inline-flex justify-center rounded-lg border border-[#dce5e2] px-4 py-2 text-sm font-bold">{{ __('messages.custom_requests.view_details') }}</a>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[#cbd9d8] p-10 text-center"><h2 class="font-bold">{{ __('messages.custom_requests.empty_title') }}</h2><p class="mt-2 text-sm text-[#607985]">{{ __('messages.custom_requests.empty_description') }}</p></div>
            @endforelse
        </section>
        {{ $requests->links() }}
    </main>
</x-account-shell>
