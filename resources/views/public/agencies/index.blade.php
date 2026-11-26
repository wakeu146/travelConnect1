<x-public-layout :title="$title" :description="$description" :robots="request()->integer('page', 1) > 1 ? 'noindex, follow' : 'index, follow'">
    <x-site-header />
    <main class="mx-auto w-full max-w-7xl flex-1 px-6 py-16 lg:px-8 lg:py-24">
        <header class="max-w-3xl">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.verified_agency') }}</p>
            <h1 class="mt-4 font-serif text-4xl tracking-tight sm:text-6xl">{{ __('messages.public_directory.agencies.heading') }}</h1>
            <p class="mt-6 text-lg leading-8 text-[#607985]">{{ __('messages.public_directory.agencies.intro') }}</p>
        </header>

        @if ($agencies->isEmpty())
            <p class="mt-12 border border-[#e7eceb] bg-white p-8 text-[#607985]">{{ __('messages.public_directory.agencies.empty') }}</p>
        @else
            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($agencies as $agency)
                    <article class="flex flex-col border border-[#e7eceb] bg-white">
                        <img src="{{ $agency['cover'] ?: asset('images/agencies/default-agency.jpg') }}" alt="{{ $agency['name'] }} travel agency" class="h-52 w-full object-cover">
                        <div class="flex flex-1 flex-col p-6">
                            <span class="w-fit border border-[#d6e8df] bg-[#f1faf5] px-3 py-1 text-xs font-bold text-[#267052]">{{ __('messages.verified_agency') }}</span>
                            <h2 class="mt-4 font-serif text-2xl">{{ $agency['name'] }}</h2>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-[#607985]">{{ $agency['description'] }}</p>
                            @if ($agency['countries'] !== [])
                                <p class="mt-4 text-sm text-[#607985]"><span class="font-semibold text-[#173042]">{{ __('messages.public_directory.agency.destinations') }}:</span> {{ implode(', ', $agency['countries']) }}</p>
                            @endif
                            @if ($agency['services'] !== [])
                                <p class="mt-2 text-sm text-[#607985]"><span class="font-semibold text-[#173042]">{{ __('messages.public_directory.agency.specialties') }}:</span> {{ implode(', ', $agency['services']) }}</p>
                            @endif
                            @if ($agency['rating'] !== null)
                                <p class="mt-4 text-sm font-semibold text-[#173042]">{{ $agency['rating'] }} / 5 · {{ __('messages.based_on_reviews', ['count' => $agency['review_count']]) }}</p>
                            @endif
                            <a href="{{ route('agency.show', ['slug' => $agency['slug'], 'lang' => app()->getLocale()]) }}" class="mt-6 inline-flex min-h-11 items-center justify-center border border-[#173042] px-4 py-3 text-sm font-bold text-[#173042] transition hover:bg-[#173042] hover:text-white">{{ __('messages.view_agency') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-10">{{ $agencies->links() }}</div>
        @endif
    </main>
    <x-site-footer />
</x-public-layout>
