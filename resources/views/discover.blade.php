<x-account-shell title="{{ __('messages.travel_agency_directory') }} | TravelConnect">
    <main
        data-discover-page
        data-search-url="{{ route('discover.search', ['lang' => app()->getLocale()]) }}"
        data-search-mode="{{ $searchMode ? 'true' : 'false' }}"
        data-recent-clear-url="{{ route('discover.recent.clear') }}"
        data-csrf-token="{{ csrf_token() }}"
        data-error-message="{{ __('messages.agency_discovery.search_error') }}"
    >
        <section data-discovery-hero class="{{ $searchMode ? 'hidden' : '' }} border-b border-[var(--account-border)] bg-[var(--account-surface-soft)]">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8 lg:py-16">
                <div class="grid gap-4">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-[var(--account-accent)]">{{ __('messages.discover_agencies') }}</p>
                    <h1 class="max-w-3xl font-serif text-4xl leading-tight tracking-tight text-[var(--account-text)] sm:text-5xl">{{ __('messages.agency_discovery.title') }}</h1>
                    <p class="max-w-2xl text-sm leading-6 text-[var(--account-text-secondary)] sm:text-base">{{ __('messages.agency_discovery.description') }}</p>
                </div>
            </div>
        </section>

        <p data-discovery-hint class="mx-auto hidden max-w-7xl px-4 pt-4 text-sm text-[var(--account-danger)] sm:px-6 lg:px-8" role="status" aria-live="polite"></p>

        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-9 sm:px-6 sm:py-12 lg:px-8">
            <section data-discovery-recent class="{{ $searchMode || count($recentAgencyCards) === 0 ? 'hidden' : 'grid' }} gap-5">
                <div class="flex flex-col gap-3 border-b border-[var(--account-border)] pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-[var(--account-text)]">{{ __('messages.agency_discovery.recent_title') }}</h2>
                        <p class="mt-1 text-sm text-[var(--account-text-secondary)]">{{ __('messages.agency_discovery.recent_description') }}</p>
                    </div>
                    <button type="button" data-discovery-clear-recent class="{{ count($recentAgencyCards) ? 'inline-flex' : 'hidden' }} min-h-10 items-center justify-center rounded-none px-3 text-sm font-semibold text-[var(--account-text-secondary)] transition hover:bg-[var(--account-hover)] hover:text-[var(--account-danger)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--account-focus)]">
                        {{ __('messages.agency_discovery.clear_recent') }}
                    </button>
                </div>

                <div data-discovery-recent-grid class="{{ count($recentAgencyCards) ? 'grid' : 'hidden' }} gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($recentAgencyCards as $agencyCard)
                        <x-discovery-agency-card :card="$agencyCard" :recent="true" />
                    @endforeach
                </div>
            </section>

            <section data-discovery-popular class="{{ $searchMode || count($popularAgencyCards) === 0 ? 'hidden' : 'grid' }} gap-5">
                <div class="border-b border-[var(--account-border)] pb-4">
                    <h2 class="text-xl font-bold text-[var(--account-text)]">{{ __('messages.agency_discovery.popular_title') }}</h2>
                    <p class="mt-1 text-sm text-[var(--account-text-secondary)]">{{ __('messages.agency_discovery.popular_description') }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($popularAgencyCards as $agencyCard)
                        <x-discovery-agency-card :card="$agencyCard" />
                    @endforeach
                </div>
            </section>

            <section data-discovery-featured class="{{ $searchMode || count($featuredAgencyCards) === 0 ? 'hidden' : 'grid' }} gap-5">
                <div class="border-b border-[var(--account-border)] pb-4">
                    <h2 class="text-xl font-bold text-[var(--account-text)]">{{ __('messages.agency_discovery.featured_title') }}</h2>
                    <p class="mt-1 text-sm text-[var(--account-text-secondary)]">{{ __('messages.agency_discovery.featured_description') }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($featuredAgencyCards as $agencyCard)
                        <x-discovery-agency-card :card="$agencyCard" :featured="true" />
                    @endforeach
                </div>
            </section>

            <section class="{{ $hasActiveSearch ? 'grid' : 'hidden' }} gap-5" data-discovery-results-section aria-busy="false">
                <div class="flex flex-col gap-3 border-b border-[var(--account-border)] pb-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-[var(--account-text)]">{{ __('messages.agency_discovery.results_title') }}</h2>
                    </div>
                </div>
                <div data-discovery-loading class="hidden items-center gap-3 py-6 text-sm text-[var(--account-text-secondary)]" role="status" aria-live="polite">
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                    {{ __('messages.agency_discovery.loading') }}
                </div>
                <div data-discovery-results-content class="{{ $hasActiveSearch ? '' : 'hidden' }}">
                    @include('discover.search-results', [
                        'agencyCards' => $agencyCards,
                        'hasActiveSearch' => $hasActiveSearch,
                        'hasLocationSearch' => $hasLocationSearch,
                        'results' => $results,
                    ])
                </div>
            </section>
        </div>
    </main>
</x-account-shell>
