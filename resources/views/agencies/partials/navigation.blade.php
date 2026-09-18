<nav aria-label="Agency workspace" class="mb-8 flex flex-wrap items-center gap-2 border-b border-[#e4e9e7] pb-4 text-sm font-semibold">
    <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('agency.dashboard') ? 'bg-[#f1f6f4] text-[#e76f51]' : 'text-[#607985] hover:bg-[#f1f6f4]' }}">{{ __('messages.overview') }}</a>
    <a href="{{ route('agency.settings', ['lang' => app()->getLocale()]) }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('agency.settings') ? 'bg-[#f1f6f4] text-[#e76f51]' : 'text-[#607985] hover:bg-[#f1f6f4]' }}">{{ __('messages.agency_profile') }}</a>
    <a href="{{ route('agency.gallery', ['lang' => app()->getLocale()]) }}" class="rounded-lg px-3 py-2 {{ request()->routeIs('agency.gallery') ? 'bg-[#f1f6f4] text-[#e76f51]' : 'text-[#607985] hover:bg-[#f1f6f4]' }}">{{ __('messages.travel_experiences') }}</a>
</nav>
