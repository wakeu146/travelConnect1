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
                <div><p class="text-sm font-semibold text-[#173042]">{{ __('messages.all_agencies') }}</p><p class="mt-1 text-sm text-[#607985]">{{ __('messages.compare_verified_travel_experts') }}</p></div>
            </div>

            <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($agencies as $agency)
                    <x-agency-card
                        :name="$agency->company_name"
                        :saved="in_array($agency->company_name, $savedAgencyNames, true)"
                        :countries="$agency->countries->pluck('name')->all()"
                        :location="$agency->address"
                        :image="$agency->logoUrl() ?? $agency->coverImageUrl()"
                        :alt="$agency->company_name"
                        :description="$agency->description"
                        :service="$agency->services->first()?->name"
                        :rating="$agency->published_reviews_count > 0 ? number_format((float) $agency->published_rating, 1) : '—'"
                        :review-count="$agency->published_reviews_count"
                        :trust-score="$agency->trust_score"
                        use-real-image="true"
                    />
                @empty
                    <div class="dashboard-panel col-span-full border-dashed border-[#cbd9d8] bg-transparent p-12 text-center">
                        <p class="font-bold">{{ __('messages.no_agencies_available') }}</p>
                    </div>
                @endforelse
            </div>

            @if ($agencies->hasPages())
                <div class="mt-8">{{ $agencies->onEachSide(1)->links() }}</div>
            @endif

        </section>

    </main>

</x-account-shell>
