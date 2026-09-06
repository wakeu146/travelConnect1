<x-public-layout :title="$pageTitle.' | TravelConnect'">
    <x-site-header />
    <main class="mx-auto max-w-4xl px-6 py-16 lg:px-8 lg:py-24"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">TravelConnect</p><h1 class="mt-4 font-serif text-5xl tracking-tight text-[#173042]">{{ $pageTitle }}</h1><p class="mt-6 max-w-2xl text-lg leading-8 text-[#607985]">{{ $pageIntro }}</p><div class="mt-12 border-t border-[#e7eceb] pt-8 text-base leading-8 text-[#607985]"><p>{{ $pageBody }}</p></div></main>
    <x-site-footer />
</x-public-layout>