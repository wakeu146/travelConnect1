<x-account-shell title="{{ __('messages.travel_agency_directory') }} | TravelConnect">

    <main data-discover-page>
        <section data-discover-hero class="border-b border-[#dbe3e5] bg-[#f1f6f4]">
            <div class="mx-auto max-w-7xl px-6 pb-12 pt-14 lg:px-8 lg:pb-16 lg:pt-20">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.all_agencies') }}</p>
                    <h1 class="mt-4 font-serif text-5xl leading-[1.05] tracking-tight text-[#173042] sm:text-6xl">{{ __('messages.find_agency_you_can_trust') }}</h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-[#607985]">{{ __('messages.compare_verified_travel_experts') }}</p>
                </div>

            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-12 lg:px-8 lg:py-16">
            <div class="flex flex-col justify-between gap-4 border-b border-[#dbe3e5] pb-6 sm:flex-row sm:items-center">
                <div>
                    <p data-discover-heading class="text-sm font-semibold text-[#173042]">{{ __('messages.all_agencies') }}</p>
                    <p class="mt-1 text-sm text-[#607985]">{{ __('messages.compare_verified_travel_experts') }}</p>
                </div>
            </div>

            <div data-discover-grid class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @if ($agencies->isEmpty())
                    <div class="dashboard-panel col-span-full border-dashed border-[#cbd9d8] bg-transparent p-12 text-center">
                        <p class="font-bold">{{ __('messages.no_agencies_available') }}</p>
                    </div>
                @else
                    @include('discover.partials.agency-cards', ['agencies' => $agencies, 'savedAgencyIds' => $savedAgencyIds])
                @endif
            </div>

            @if ($agencies->hasPages())
                <div data-discover-pagination class="mt-8">{{ $agencies->onEachSide(1)->links() }}</div>
            @endif

        </section>

        <div data-discover-mobile-search class="fixed inset-0 z-[100] hidden flex-col bg-white text-[#173042] dark:bg-[#102936] dark:text-white" aria-hidden="true">
            <div class="flex items-center gap-3 border-b border-[#dbe3e5] px-4 py-3 dark:border-white/15">
                <button type="button" data-mobile-search-close class="flex h-10 w-10 shrink-0 items-center justify-center text-[#173042] dark:text-white" aria-label="{{ __('messages.agency_search_back') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="m15 18-6-6 6-6M9 12h12"/></svg>
                </button>
                <input data-mobile-search-input type="text" role="searchbox" class="min-w-0 flex-1 border-0 bg-transparent py-2 text-base outline-none placeholder:text-[#9aadb2]" placeholder="{{ __('messages.search_agencies_or_destinations') }}" aria-label="{{ __('messages.search_agencies_or_destinations') }}" autocomplete="off">
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5">
                <div data-mobile-recent-searches>
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <h2 class="text-sm font-bold">{{ __('messages.agency_recent_searches') }}</h2>
                        <button type="button" data-mobile-search-clear-all class="text-sm font-semibold text-[#e76f51]">{{ __('messages.agency_recent_clear_all') }}</button>
                    </div>
                    <p data-mobile-recent-empty class="hidden text-sm text-[#607985] dark:text-white/70">{{ __('messages.agency_recent_empty') }}</p>
                    <ul data-mobile-recent-list class="grid gap-1"></ul>
                </div>
                <div data-mobile-search-results class="hidden" aria-live="polite"></div>
            </div>
        </div>

    </main>

</x-account-shell>
