<x-public-layout :title="$title" :description="$description" :image="$agency['cover']" :schema="$schema">
    <x-site-header />
    <main class="flex-1">
        <div class="mx-auto max-w-7xl px-6 pt-8 text-sm text-[#607985] lg:px-8">
            <a href="{{ route('agencies.index', ['lang' => app()->getLocale()]) }}" class="font-semibold hover:text-[#e76f51]">{{ __('messages.agency_directory') }}</a>
            <span class="mx-2">/</span><span>{{ $agency['name'] }}</span>
        </div>
        <section class="relative mt-6 min-h-[360px] overflow-hidden bg-[#173042] sm:min-h-[440px]">
            <img src="{{ $agency['cover'] }}" alt="{{ $agency['name'] }} travel agency" class="absolute inset-0 h-full w-full object-cover opacity-70">
            <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(16,39,54,.92),rgba(23,48,66,.18))]"></div>
            <div class="relative mx-auto flex min-h-[360px] max-w-7xl items-end px-6 pb-10 sm:min-h-[440px] lg:px-8">
                <div class="max-w-3xl text-white">
                    <span class="inline-flex border border-white/30 bg-white px-3 py-1.5 text-xs font-bold text-[#173042]">{{ __('messages.verified_agency') }}</span>
                    <h1 class="mt-5 font-serif text-4xl leading-tight sm:text-6xl">{{ __('messages.public_directory.agency.heading', ['agency' => $agency['name']]) }}</h1>
                    @if ($agency['countries'] !== [])
                        <p class="mt-4 text-base text-white/80">{{ implode(' · ', $agency['countries']) }}</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-7xl gap-12 px-6 py-12 lg:grid-cols-[1.25fr_.75fr] lg:px-8 lg:py-16">
            <div>
                <h2 class="font-serif text-3xl tracking-tight sm:text-4xl">{{ __('messages.public_directory.agency.about') }}</h2>
                @if (filled($agency['description']))
                    <p class="mt-5 max-w-2xl text-base leading-8 text-[#607985]">{{ $agency['description'] }}</p>
                @endif
                <div class="mt-8 grid gap-4 border-y border-[#e7eceb] py-6 sm:grid-cols-2">
                    @if ($agency['countries'] !== [])
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.public_directory.agency.destinations') }}</p>
                            <p class="mt-2 font-semibold text-[#173042]">{{ implode(', ', $agency['countries']) }}</p>
                        </div>
                    @endif
                    @if ($agency['services'] !== [])
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.15em] text-[#607985]">{{ __('messages.public_directory.agency.specialties') }}</p>
                            <p class="mt-2 font-semibold text-[#173042]">{{ implode(', ', $agency['services']) }}</p>
                        </div>
                    @endif
                </div>

                <section class="mt-12">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 class="font-serif text-3xl tracking-tight">{{ __('messages.public_directory.agency.packages') }}</h2>
                        @if ($agency['review_count'] > 0)
                            <p class="text-sm font-semibold text-[#607985]">{{ $agency['rating'] }} / 5 · {{ __('messages.based_on_reviews', ['count' => $agency['review_count']]) }}</p>
                        @endif
                    </div>
                    @if ($packages->isEmpty())
                        <p class="mt-6 border border-dashed border-[#cbd9d8] p-6 text-sm leading-7 text-[#607985]">{{ __('messages.public_directory.agency.no_packages') }}</p>
                    @else
                        <div class="mt-6 grid gap-5 sm:grid-cols-2">
                            @foreach ($packages as $package)
                                @php($packageImage = $package->images->first()?->publicUrl())
                                <article class="flex flex-col border border-[#e7eceb] bg-white">
                                    <img src="{{ $packageImage ?: asset('images/destinations/new-york.jpg') }}" alt="{{ $package->title }} travel package in {{ $package->destination }}" class="h-48 w-full object-cover">
                                    <div class="flex flex-1 flex-col p-5">
                                        <h3 class="font-serif text-xl">{{ $package->title }}</h3>
                                        <p class="mt-2 text-sm text-[#607985]">{{ $package->destination }} · {{ __('messages.public_directory.package.duration', ['count' => $package->duration_days]) }}</p>
                                        <p class="mt-3 text-sm font-semibold text-[#173042]">{{ __('messages.public_directory.package.price_from', ['price' => number_format((float) $package->price_from, 0).' '.config('travelconnect.currency')]) }}</p>
                                        <a href="{{ route('packages.show', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="mt-5 inline-flex min-h-11 items-center justify-center border border-[#173042] px-4 py-3 text-sm font-bold text-[#173042] transition hover:bg-[#173042] hover:text-white">{{ __('messages.view_package') }}</a>
                                        <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-3 inline-flex min-h-11 items-center justify-center bg-[#e76f51] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.log_in_to_continue') }}</a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="mt-12 border-t border-[#e7eceb] pt-10">
                    <h2 class="font-serif text-3xl tracking-tight">{{ __('messages.public_directory.agency.reviews') }}</h2>
                    @forelse ($reviews as $review)
                        <article class="border-b border-[#e7eceb] py-5">
                            <p class="text-sm font-semibold text-[#173042]">{{ $review->rating }} / 5</p>
                            <p class="mt-2 text-sm leading-7 text-[#607985]">{{ $review->comment }}</p>
                            <time class="mt-2 block text-xs text-[#84969b]" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->translatedFormat('j M Y') }}</time>
                        </article>
                    @empty
                        <p class="mt-5 text-sm leading-7 text-[#607985]">{{ __('messages.public_directory.agency.no_reviews') }}</p>
                    @endforelse
                </section>
            </div>

            <aside>
                <div class="border border-[#e7eceb] bg-white p-6">
                    <h2 class="text-lg font-bold text-[#173042]">{{ __('messages.connect_with_this_agency') }}</h2>
                    <p class="mt-3 text-sm leading-6 text-[#607985]">{{ __('messages.tell_the_agency') }}</p>
                    <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-5 flex min-h-12 w-full items-center justify-center bg-[#e76f51] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.log_in_to_continue') }} · {{ __('messages.public_directory.agency.inquiry') }}</a>
                    <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-3 flex min-h-12 w-full items-center justify-center border border-[#d7e0e1] px-5 py-3 text-sm font-bold text-[#173042] transition hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.log_in_to_continue') }} · {{ __('messages.public_directory.agency.favorite') }}</a>
                    <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-3 flex min-h-12 w-full items-center justify-center border border-[#d7e0e1] px-5 py-3 text-sm font-bold text-[#173042] transition hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.log_in_to_continue') }} · {{ __('messages.public_directory.agency.booking') }}</a>
                </div>
            </aside>
        </section>
    </main>
    <x-site-footer />
</x-public-layout>
