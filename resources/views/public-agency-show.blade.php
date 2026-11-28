<x-public-layout :title="$seoTitle" :description="$seoDescription" :image="asset($agency['cover'])" :schema="$schema">
    <x-site-header />
    <main class="mx-auto w-full max-w-7xl flex-1 px-6 py-10 lg:px-8 lg:py-14">
        <nav class="text-sm text-[#607985]" aria-label="{{ __('messages.agency_directory') }}">
            <a href="{{ route('home') }}" class="font-semibold hover:text-[#e76f51]">{{ __('messages.home') }}</a>
            <span class="mx-2">/</span>
            <span>{{ __('messages.agency_directory') }}</span>
            <span class="mx-2">/</span>
            <span>{{ $agency['name'] }}</span>
        </nav>

        <section class="mt-8 grid gap-8 border-b border-[#e7eceb] pb-10 md:grid-cols-[1.1fr_.9fr] md:items-center">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.verified_agency') }}</p>
                <h1 class="mt-4 font-serif text-4xl leading-tight sm:text-6xl">{{ $agency['name'] }}</h1>
                <p class="mt-4 text-base leading-7 text-[#607985]">{{ $agency['service'] }} <span class="mx-2">|</span> {{ implode(' · ', $agency['countries']) }}</p>
                <h2 class="mt-8 font-serif text-2xl">{{ __('messages.about_this_agency') }}</h2>
                <p class="mt-3 max-w-2xl leading-7 text-[#607985]">{{ $agency['description'] }}</p>
            </div>
            <img src="{{ asset($agency['cover']) }}" alt="{{ $agency['name'] }}" class="h-64 w-full border border-[#e7eceb] object-cover sm:h-80">
        </section>

        @include('components.agency-packages', ['packages' => $packages])

        <aside class="mt-12 border border-[#e7eceb] bg-[#f1f6f4] p-6 sm:p-8">
            <h2 class="font-serif text-2xl">{{ __('messages.connect_with_this_agency') }}</h2>
            <p class="mt-3 max-w-2xl leading-7 text-[#607985]">{{ __('messages.tell_the_agency') }}</p>
            <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-5 inline-flex border border-[#e76f51] bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">
                {{ __('messages.send_an_inquiry') }}
            </a>
        </aside>
    </main>
    <x-site-footer />
</x-public-layout>
