<x-account-shell title="{{ __('messages.dashboard') }} | TravelConnect">
    <main id="overview" class="mx-auto max-w-7xl overflow-x-hidden px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.traveler_workspace') }}</p><h1 class="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">{{ __('messages.good_to_see_you', ['name' => $user->name]) }}</h1><p class="mt-3 max-w-xl text-sm leading-6 text-[#607985]">{{ __('messages.dashboard_intro') }}</p></div><a href="{{ route('account.discover', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95f42]">{{ __('messages.explore_agencies') }} <span aria-hidden="true">&rarr;</span></a></div>
        <div class="mt-9 grid gap-4 sm:grid-cols-3">
            <a href="#saved" class="dashboard-panel dashboard-kpi border-[#e7eceb] bg-white p-5 transition hover:-translate-y-0.5 hover:border-[#e76f51]">
                <div class="flex items-center justify-between gap-3"><p class="text-sm font-bold">{{ __('messages.saved_agencies') }}</p><span class="traveler-stat-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M6 4h12v16l-6-3-6 3V4Z"/></svg></span></div>
                <p class="mt-5 text-3xl font-bold">{{ $savedAgencyCount }}</p><p class="mt-1 text-xs text-[#607985]">{{ __('messages.agencies_you_want_to_revisit') }}</p>
            </a>
            <a href="#activity" class="dashboard-panel dashboard-kpi border-[#e7eceb] bg-white p-5 transition hover:-translate-y-0.5 hover:border-[#e76f51]">
                <div class="flex items-center justify-between gap-3"><p class="text-sm font-bold">{{ __('messages.open_inquiries') }}</p><span class="traveler-stat-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-4 2v-6.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8M8 14h5"/></svg></span></div>
                <p class="mt-5 text-3xl font-bold">{{ $openInquiryCount }}</p><p class="mt-1 text-xs text-[#607985]">{{ __('messages.conversations_with_agencies') }}</p>
            </a>
            <a href="#activity" class="dashboard-panel dashboard-kpi border-[#e7eceb] bg-white p-5 transition hover:-translate-y-0.5 hover:border-[#e76f51]">
                <div class="flex items-center justify-between gap-3"><p class="text-sm font-bold">{{ __('messages.reviews_written') }}</p><span class="traveler-stat-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg></span></div>
                <p class="mt-5 text-3xl font-bold">{{ $publishedReviewCount }}</p><p class="mt-1 text-xs text-[#607985]">{{ __('messages.your_published_experiences') }}</p>
            </a>
        </div>
        <section id="saved" class="mt-10">
            <div class="flex items-end justify-between">
                <div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.your_collection') }}</p><h2 class="mt-2 font-serif text-3xl tracking-tight">{{ __('messages.saved_for_later') }}</h2></div>
                <a href="{{ route('account.saved', ['lang' => app()->getLocale()]) }}" class="text-sm font-bold text-[#e76f51]">{{ __('messages.view_all') }}</a>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                @forelse ($savedAgencies as $agency)
                    <article data-saved-card class="dashboard-panel group relative border-[#e7eceb] bg-white p-5 transition hover:border-[#e76f51]">
                        <a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($agency->company_name), 'lang' => app()->getLocale()]) }}" class="block pr-9">
                            <h3 class="font-bold">{{ $agency->company_name }}</h3>
                            <p class="mt-2 text-sm text-[#607985]">{{ $agency->countries->first()?->name ?? __('messages.travel_agency') }}</p>
                        </a>
                        <button type="button" data-favorite-toggle data-remove-saved-agency="true" data-agency-slug="{{ \Illuminate\Support\Str::slug($agency->company_name) }}" data-saved="true" data-authenticated="true" aria-pressed="true" aria-label="{{ __('messages.remove_saved_agency', ['name' => $agency->company_name]) }}" title="{{ __('messages.remove_saved_agency', ['name' => $agency->company_name]) }}" class="delete-icon-button absolute right-3 top-3">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>
                        </button>
                    </article>
                @empty
                    <div class="dashboard-panel col-span-full border-dashed border-[#cbd9d8] bg-transparent p-8 text-center">
                        <p class="font-bold">{{ __('messages.no_saved_agencies') }}</p>
                        <a href="{{ route('account.discover', ['lang' => app()->getLocale()]) }}" class="mt-4 inline-flex rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-bold text-white">{{ __('messages.discover_agencies') }}</a>
                    </div>
                @endforelse
            </div>
        </section>
        <section id="activity" class="mt-10 border-t border-[#e4e9e7] pt-10"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.my_activity') }}</p><h2 class="mt-2 font-serif text-3xl tracking-tight">{{ __('messages.keep_planning') }}</h2></div><a href="{{ route('account.activity', ['lang' => app()->getLocale()]) }}" class="text-sm font-bold text-[#e76f51]">{{ __('messages.view_all') }}</a></div><p class="mt-4 max-w-2xl text-sm leading-7 text-[#607985]">{{ __('messages.keep_planning_text') }}</p><div class="mt-6 grid gap-4 lg:grid-cols-2"><div class="dashboard-panel border-[#e7eceb] bg-white p-5"><h3 class="font-bold">{{ __('messages.open_inquiries') }}</h3><div class="mt-4 grid gap-3">@forelse ($recentInquiries as $inquiry)<div class="border-b border-[#e7eceb] pb-3 last:border-0 last:pb-0"><p class="text-sm font-semibold">{{ $inquiry->subject }}</p><p class="mt-1 text-xs text-[#607985]">{{ $inquiry->agency->company_name }} · {{ ucfirst($inquiry->status->value) }}</p></div>@empty<p class="text-sm text-[#607985]">{{ __('messages.no_inquiries_yet') }}</p>@endforelse</div></div><div class="dashboard-panel border-[#e7eceb] bg-white p-5"><h3 class="font-bold">{{ __('messages.reviews_written') }}</h3><div class="mt-4 grid gap-3">@forelse ($recentReviews as $review)<div class="border-b border-[#e7eceb] pb-3 last:border-0 last:pb-0"><div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold">{{ $review->agency->company_name }}</p><span class="text-sm text-[#e76f51]">{{ $review->rating }} ★</span></div><p class="mt-1 truncate text-xs text-[#607985]">{{ $review->comment }}</p></div>@empty<p class="text-sm text-[#607985]">{{ __('messages.no_reviews_yet') }}</p>@endforelse</div></div></div></section>
        <section class="mt-10 border-t border-[#e4e9e7] pt-10"><div class="flex items-end justify-between"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.recommended_for_you') }}</p><h2 class="mt-2 font-serif text-3xl tracking-tight">{{ __('messages.trusted_travel_specialists') }}</h2></div><a href="{{ route('account.discover', ['lang' => app()->getLocale()]) }}" class="text-sm font-bold text-[#e76f51]">{{ __('messages.explore_agencies') }}</a></div><div class="mt-5 grid gap-4 sm:grid-cols-3">@foreach ($recommendedAgencies as $agency)<a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($agency->company_name), 'lang' => app()->getLocale()]) }}" class="dashboard-panel border-[#e7eceb] bg-white p-5 transition hover:border-[#e76f51]"><h3 class="font-bold">{{ $agency->company_name }}</h3><p class="mt-2 text-sm text-[#607985]">{{ $agency->description }}</p></a>@endforeach</div></section>
    </main>
</x-account-shell>
