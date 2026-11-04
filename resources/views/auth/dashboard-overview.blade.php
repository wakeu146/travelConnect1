<x-account-shell title="{{ __('messages.dashboard') }} | TravelConnect">
    <main id="overview" class="mx-auto max-w-7xl overflow-x-hidden px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.traveler_workspace') }}</p><h1 class="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">{{ __('messages.good_to_see_you', ['name' => $user->name]) }}</h1><p class="mt-3 max-w-xl text-sm leading-6 text-[#607985]">{{ __('messages.dashboard_intro') }}</p></div><a href="{{ route('account.discover', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95f42]">{{ __('messages.explore_agencies') }} <span aria-hidden="true">&rarr;</span></a></div>
        <div class="mt-9 flex flex-wrap gap-3"><a href="{{ route('account.custom-requests.index', ['lang' => app()->getLocale()]) }}" class="rounded-xl border border-[#e7eceb] bg-white px-4 py-3 text-sm font-bold text-[#173042]">{{ __('messages.custom_requests.my_requests') }}</a><a href="{{ route('account.custom-requests.create', ['lang' => app()->getLocale()]) }}" class="rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white">{{ __('messages.custom_requests.create') }}</a></div>
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
        <section id="activity" class="mt-10 border-t border-[#e4e9e7] pt-10"><div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.my_activity') }}</p><h2 class="mt-2 font-serif text-3xl tracking-tight">{{ __('messages.keep_planning') }}</h2></div><a href="{{ route('account.bookings', ['lang' => app()->getLocale()]) }}" class="text-sm font-bold text-[#e76f51]">{{ __('messages.view_all') }}</a></div><p class="mt-4 max-w-2xl text-sm leading-7 text-[#607985]">{{ __('messages.keep_planning_text') }}</p><div class="mt-6 grid gap-4 lg:grid-cols-2"><div class="dashboard-panel border-[#e7eceb] bg-white p-5"><h3 class="font-bold">{{ __('messages.open_inquiries') }}</h3><div class="mt-4 grid gap-3">@forelse ($recentInquiries as $inquiry)<div class="border-b border-[#e7eceb] pb-3 last:border-0 last:pb-0"><p class="text-sm font-semibold">{{ $inquiry->subject }}</p><p class="mt-1 text-xs text-[#607985]">{{ $inquiry->agency->company_name }} · {{ ucfirst($inquiry->status->value) }}</p></div>@empty<p class="text-sm text-[#607985]">{{ __('messages.no_inquiries_yet') }}</p>@endforelse</div></div><div class="dashboard-panel border-[#e7eceb] bg-white p-5"><h3 class="font-bold">{{ __('messages.reviews_written') }}</h3><div class="mt-4 grid gap-3">@forelse ($recentReviews as $review)<div class="border-b border-[#e7eceb] pb-3 last:border-0 last:pb-0"><div class="flex items-center justify-between gap-3"><p class="text-sm font-semibold">{{ $review->agency->company_name }}</p><span class="text-sm text-[#e76f51]">{{ $review->rating }} ★</span></div><p class="mt-1 truncate text-xs text-[#607985]">{{ $review->comment }}</p></div>@empty<p class="text-sm text-[#607985]">{{ __('messages.no_reviews_yet') }}</p>@endforelse</div></div></div></section>
        <section class="mt-10 border-t border-[#e4e9e7] pt-10">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.discover_agencies') }}</p>
                <h2 class="mt-2 font-serif text-3xl tracking-tight">{{ __('messages.top_agencies_by_trust_score') }}</h2>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($recommendedAgencies as $agency)
                    @php($agencyLogo = $agency->logoUrl())
                    @php($agencyCover = $agency->coverImageUrl())
                    @php($agencyInitials = collect(explode(' ', $agency->company_name))->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode(''))
                    <a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($agency->company_name), 'lang' => app()->getLocale()]) }}" class="dashboard-panel group overflow-hidden border-[#e7eceb] bg-white transition hover:border-[#e76f51]">
                        <div class="relative h-32 overflow-hidden bg-[#edf4f3]">
                            @if ($agencyCover)
                                <img src="{{ $agencyCover }}" alt="" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                            @else
                                <div data-agency-cover-fallback class="h-full w-full bg-[#edf4f3]" aria-hidden="true"></div>
                            @endif
                            <span class="absolute right-3 top-3 rounded bg-white/95 px-2.5 py-1 text-xs font-bold text-[#55707c]">#{{ $loop->iteration }}</span>
                        </div>
                        <div class="px-5 pb-5">
                            <div class="flex items-start gap-3">
                                <div class="relative -mt-8 flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border-4 border-white bg-[#edf4f3] text-sm font-bold text-[#173042] shadow-sm">
                                    @if ($agencyLogo)
                                        <img src="{{ $agencyLogo }}" alt="{{ __('messages.agency_logo_alt', ['name' => $agency->company_name]) }}" class="h-full w-full object-cover">
                                    @else
                                        <span data-agency-logo-fallback aria-hidden="true">{{ $agencyInitials }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 pt-2">
                                    <h3 class="break-words font-bold text-[#173042]">{{ $agency->company_name }}</h3>
                                    <p class="mt-1 text-sm text-[#607985]">{{ $agency->countries->pluck('name')->join(', ') ?: __('messages.travel_agency') }}</p>
                                    @if (filled($agency->address))<p class="mt-1 truncate text-xs text-[#607985]">{{ __('messages.agency_location') }}: {{ $agency->address }}</p>@endif
                                </div>
                            </div>
                        @if ($agency->services->isNotEmpty())
                                <p class="mt-4 text-sm text-[#607985]">{{ $agency->services->pluck('name')->join(' · ') }}</p>
                        @endif
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-[#e7eceb] pt-3 text-sm">
                                @if ($agency->published_reviews_count > 0)
                                    <span class="font-semibold text-[#e76f51]">{{ number_format((float) $agency->published_rating, 1) }} <span aria-hidden="true">★</span></span>
                                @else
                                    <span class="text-[#607985]">{{ __('messages.no_reviews_yet') }}</span>
                                @endif
                                <span class="font-semibold text-[#173042]">{{ __('messages.trust_score') }}: {{ $agency->trust_score }}</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="dashboard-panel col-span-full border-dashed border-[#cbd9d8] bg-transparent p-8 text-center text-sm text-[#607985]">{{ __('messages.no_agencies_available') }}</p>
                @endforelse
            </div>
            <a href="{{ route('discover', ['lang' => app()->getLocale()]) }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.view_all_agencies') }}</a>
        </section>
    </main>
</x-account-shell>
