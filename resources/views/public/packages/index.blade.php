<x-public-layout :title="$title" :description="$description" :robots="request()->integer('page', 1) > 1 ? 'noindex, follow' : 'index, follow'">
    <x-site-header />
    <main class="mx-auto w-full max-w-7xl flex-1 px-6 py-16 lg:px-8 lg:py-24">
        <header class="max-w-3xl">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.travel_package') }}</p>
            <h1 class="mt-4 font-serif text-4xl tracking-tight sm:text-6xl">{{ __('messages.public_directory.packages.heading') }}</h1>
            <p class="mt-6 text-lg leading-8 text-[#607985]">{{ __('messages.public_directory.packages.intro') }}</p>
        </header>

        @if ($packages->isEmpty())
            <p class="mt-12 border border-[#e7eceb] bg-white p-8 text-[#607985]">{{ __('messages.public_directory.packages.empty') }}</p>
        @else
            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($packages as $package)
                    @php($packageImage = $package->images->first()?->publicUrl())
                    <article class="flex flex-col border border-[#e7eceb] bg-white">
                        <img src="{{ $packageImage ?: asset('images/destinations/new-york.jpg') }}" alt="{{ $package->title }} travel package in {{ $package->destination }}" class="h-52 w-full object-cover">
                        <div class="flex flex-1 flex-col p-6">
                            <p class="text-xs font-bold uppercase tracking-[.15em] text-[#e76f51]">{{ $package->destination }}</p>
                            <h2 class="mt-3 font-serif text-2xl">{{ $package->title }}</h2>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-[#607985]">{{ $package->summary ?: $package->description }}</p>
                            <p class="mt-4 text-sm text-[#607985]">{{ $package->agency->company_name }} · {{ __('messages.public_directory.package.duration', ['count' => $package->duration_days]) }}</p>
                            <p class="mt-2 text-sm font-semibold text-[#173042]">{{ __('messages.public_directory.package.price_from', ['price' => number_format((float) $package->price_from, 0).' '.config('travelconnect.currency')]) }}</p>
                            @if ($package->availabilities->isNotEmpty())
                                <p class="mt-2 text-xs text-[#607985]">{{ __('messages.public_directory.next_departure', ['date' => $package->availabilities->first()->departure_date->translatedFormat('j M Y')]) }}</p>
                            @endif
                            <a href="{{ route('packages.show', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="mt-6 inline-flex min-h-11 items-center justify-center border border-[#173042] px-4 py-3 text-sm font-bold text-[#173042] transition hover:bg-[#173042] hover:text-white">{{ __('messages.view_package') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-10">{{ $packages->links() }}</div>
        @endif
    </main>
    <x-site-footer />
</x-public-layout>
