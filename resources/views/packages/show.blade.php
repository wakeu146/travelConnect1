<x-public-layout :title="$title" :description="$description" :schema="$schema" :image="$images[0] ?? null">
    <x-site-header />
    <main class="mx-auto w-full max-w-7xl flex-1 px-6 py-12 lg:px-8 lg:py-16">
        <nav class="text-sm text-[#607985]" aria-label="Breadcrumb">
            <a href="{{ route('packages.index', ['lang' => app()->getLocale()]) }}" class="font-semibold hover:text-[#e76f51]">{{ __('messages.travel_package') }}</a>
            <span class="mx-2">/</span><span>{{ $package->title }}</span>
        </nav>

        <div class="mt-8 grid gap-10 lg:grid-cols-[1.2fr_.8fr]">
            <section>
                @if ($images !== [])
                    <img src="{{ $images[0] }}" alt="{{ $package->title }} travel package in {{ $package->destination }}" class="h-[360px] w-full object-cover sm:h-[480px]">
                @else
                    <img src="{{ asset('images/destinations/new-york.jpg') }}" alt="{{ __('messages.public_directory.package.no_image') }}: {{ $package->destination }}" class="h-[360px] w-full object-cover sm:h-[480px]">
                @endif
                <p class="mt-8 text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ $package->destination }}</p>
                <h1 class="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">{{ __('messages.public_directory.package.heading', ['package' => $package->title]) }}</h1>
                @if (filled($package->summary))
                    <p class="mt-5 text-lg leading-8 text-[#607985]">{{ $package->summary }}</p>
                @endif
                <div class="mt-8 border-t border-[#e7eceb] pt-6">
                    <h2 class="font-serif text-2xl">{{ __('messages.public_directory.packages.heading') }}</h2>
                    <p class="mt-4 leading-8 text-[#607985]">{{ $package->description }}</p>
                </div>
            </section>

            <aside>
                <div class="border border-[#e7eceb] bg-white p-6 sm:p-8">
                    <p class="text-sm text-[#607985]">{{ __('messages.public_directory.package.agency', ['agency' => $package->agency->company_name]) }}</p>
                    <p class="mt-5 font-serif text-3xl">{{ __('messages.public_directory.package.price_from', ['price' => number_format((float) $package->price_from, 0).' '.config('travelconnect.currency')]) }}</p>
                    <p class="mt-3 text-sm text-[#607985]">{{ __('messages.public_directory.package.duration', ['count' => $package->duration_days]) }}</p>
                    <section class="mt-8 border-t border-[#e7eceb] pt-6">
                        <h2 class="font-semibold text-[#173042]">{{ __('messages.public_directory.package.departures') }}</h2>
                        <ul class="mt-3 grid gap-2 text-sm text-[#607985]">
                            @foreach ($activeAvailabilities as $availability)
                                <li class="border border-[#e7eceb] px-4 py-3">{{ $availability->departure_date->translatedFormat('j M Y') }}</li>
                            @endforeach
                        </ul>
                    </section>
                    <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-8 flex min-h-12 w-full items-center justify-center bg-[#e76f51] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.log_in_to_continue') }}</a>
                    <a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($package->agency->company_name), 'lang' => app()->getLocale()]) }}" class="mt-4 block text-center text-sm font-semibold text-[#173042] underline underline-offset-4">{{ __('messages.view_agency') }}</a>
                </div>
            </aside>
        </div>
    </main>
    <x-site-footer />
</x-public-layout>
