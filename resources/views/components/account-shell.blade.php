@props(['title' => 'TravelConnect'])
<x-auth-layout :title="$title">
    <div class="min-h-screen bg-[#f7f8f6] text-[#173042]">
        <header class="border-b border-[#e4e9e7] bg-white px-5 py-4 sm:px-8"><div class="mx-auto flex max-w-6xl items-center justify-between gap-4"><a href="{{ route('dashboard', ['lang' => app()->getLocale()]) }}" class="text-lg font-semibold">Travel<span class="font-normal text-[#e76f51]">Connect</span></a><nav class="flex items-center gap-4 text-sm font-semibold"><a href="{{ route('dashboard', ['lang' => app()->getLocale()]) }}" class="text-[#607985] hover:text-[#e76f51]">{{ __('messages.dashboard') }}</a><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="text-[#607985] hover:text-[#e76f51]">{{ __('messages.logout') }}</button></form></nav></div></header>
        {{ $slot }}
    </div>
</x-auth-layout>
