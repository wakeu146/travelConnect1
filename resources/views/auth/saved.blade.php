<x-account-shell title="{{ __('messages.saved_agencies') }} | TravelConnect">
    <main class="min-h-screen bg-[#f7f8f6] px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <div class="mx-auto max-w-6xl">
            <div class="flex flex-col justify-between gap-4 border-b border-[#e4e9e7] pb-7 sm:flex-row sm:items-end"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.your_collection') }}</p><h1 class="mt-2 font-serif text-4xl tracking-tight">{{ __('messages.saved_for_later') }}</h1><p class="mt-3 text-sm text-[#607985]">{{ __('messages.no_saved_agencies_hint') }}</p></div><a href="{{ route('discover', ['lang' => app()->getLocale()]) }}" class="inline-flex rounded-xl bg-[#e76f51] px-4 py-3 text-sm font-bold text-white">{{ __('messages.discover_agencies') }}</a></div>
            <div class="mt-8 grid gap-5 md:grid-cols-3">
                @forelse ($savedAgencies as $agency)
                    <article data-saved-card class="dashboard-panel group relative overflow-hidden border-[#e7eceb] bg-white transition hover:-translate-y-1 hover:shadow-lg">
                        <a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($agency->company_name), 'lang' => app()->getLocale()]) }}" class="block">
                            <div class="relative flex h-44 items-center justify-center overflow-hidden bg-[#173042]">
                                @php($agencyImage = $agency->coverImageUrl() ?: $agency->logoUrl())
                                @isset($agencyImage)
                                    <img src="{{ $agencyImage }}" alt="{{ $agency->company_name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="text-5xl font-bold text-white">{{ str($agency->company_name)->substr(0, 1)->upper() }}</span>
                                @endisset
                                <span data-saved-badge class="absolute bottom-4 left-4 rounded-md bg-white px-2 py-1 text-[10px] font-bold text-[#173042]">{{ __('messages.saved') }}</span>
                            </div>
                            <div class="p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 class="truncate font-bold">{{ $agency->company_name }}</h2>
                                    <span class="shrink-0 text-sm font-bold text-[#e76f51]">{{ $agency->published_rating ? number_format($agency->published_rating, 1) : '—' }} ★</span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($agency->countries as $country)
                                        <span class="rounded-full bg-[#edf4f3] px-2.5 py-1 text-xs font-medium text-[#55707c]">{{ $country->name }}</span>
                                    @empty
                                        <span class="text-sm text-[#607985]">{{ __('messages.travel_agency') }}</span>
                                    @endforelse
                                </div>
                            </div>
                        </a>
                        <button type="button" data-favorite-toggle data-remove-saved-agency="true" data-agency-slug="{{ \Illuminate\Support\Str::slug($agency->company_name) }}" data-saved="true" data-authenticated="true" aria-pressed="true" aria-label="{{ __('messages.remove_saved_agency', ['name' => $agency->company_name]) }}" title="{{ __('messages.remove_saved_agency', ['name' => $agency->company_name]) }}" class="delete-icon-button absolute right-3 top-3 z-10">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>
                        </button>
                    </article>
                @empty
                    <div class="dashboard-panel col-span-full border-dashed border-[#cbd9d8] bg-transparent p-12 text-center">
                        <p class="font-bold">{{ __('messages.no_saved_agencies') }}</p>
                        <p class="mt-2 text-sm text-[#607985]">{{ __('messages.no_saved_agencies_hint') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </main>
</x-account-shell>
