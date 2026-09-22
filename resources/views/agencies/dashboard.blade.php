<x-auth-layout title="{{ __('messages.agency_dashboard') }} | TravelConnect">
    @php
        $user = auth()->user();
        $agency = $agency ?? $user->agency;
    @endphp

    <div data-agency-shell class="dashboard-shell min-h-screen bg-[#f7f8f6] text-[#173042]">
        <aside data-agency-sidebar class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-[#e4e9e7] bg-white px-5 py-6 shadow-xl transition-transform duration-300 lg:translate-x-0 lg:shadow-none">
            <div class="flex items-center justify-between gap-2 px-2">
                <a href="{{ route('home', ['lang' => app()->getLocale()]) }}" class="flex items-center gap-3 text-lg font-semibold">
                    <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-[#f1f6f4]"><img src="{{ asset('images/logo.png') }}" alt="TravelConnect logo" class="h-full w-full object-contain"></span>
                    <span>Travel<span class="font-normal text-[#e76f51]">Connect</span></span>
                </a>
                <button type="button" data-agency-close class="flex h-9 w-9 items-center justify-center rounded-lg text-[#607985] hover:bg-[#f1f6f4] lg:hidden" aria-label="{{ __('messages.close_navigation') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="mt-10">
                <p class="px-3 text-[10px] font-bold uppercase tracking-[.2em] text-[#9aadb2]">{{ __('messages.agency_workspace') }}</p>
                <nav class="mt-3 grid gap-1" aria-label="{{ __('messages.dashboard_navigation') }}">
                    <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="flex items-center gap-3 rounded-xl bg-[#f1f6f4] px-3 py-3 text-sm font-bold text-[#e76f51]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                        {{ __('messages.overview') }}
                    </a>
                    <a href="{{ route('agency.profile', ['lang' => app()->getLocale()]) }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-[#607985] transition hover:bg-[#f7f8f6] hover:text-[#173042]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0"/><path d="M17.5 5.5A2.5 2.5 0 0 1 20 8v8"/></svg>
                        {{ __('messages.profile') }}
                    </a>
                    <a href="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-[#607985] transition hover:bg-[#f7f8f6] hover:text-[#173042]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Zm0 9 8 4.5 8-4.5M4 7.5V16l8 4.5V12"/></svg>
                        {{ __('messages.travel_packages') }}
                    </a>
                </nav>
            </div>

            <div class="mt-auto border-t border-[#e4e9e7] pt-5">
                <div class="flex items-center gap-3 px-2">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#173042] text-sm font-bold text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold">{{ $user->name }}</p>
                        <p class="truncate text-xs text-[#607985]">{{ $agency->company_name }}</p>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="mt-4">@csrf<button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-[#607985] hover:bg-[#fff1ed] hover:text-[#e76f51]"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M10 5H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h5M13 8l4 4-4 4M8 12h9"/></svg>{{ __('messages.logout') }}</button></form>
            </div>
        </aside>

        <div data-agency-backdrop class="fixed inset-0 z-40 hidden bg-[#102936]/40 opacity-0 transition-opacity duration-300 lg:hidden"></div>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-[#e4e9e7] bg-white/95 px-5 py-4 backdrop-blur sm:px-8">
                <div class="flex items-center gap-4">
                    <button type="button" data-agency-open class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#dbe3e5] text-[#173042] lg:hidden" aria-label="{{ __('messages.open_navigation') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.agency_dashboard') }}</p>
                        <h1 class="mt-1 text-xl font-bold text-[#173042]">{{ $agency->company_name }}</h1>
                    </div>
                    <a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($agency->company_name), 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">
                        {{ __('messages.view_public_profile') }}
                    </a>
                </div>
            </header>

            <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.agency_workspace') }}</p>
                        <h2 class="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">{{ __('messages.welcome_agency_user', ['name' => $user->name]) }}</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-[#607985]">{{ __('messages.agency_dashboard_intro') }}</p>
                    </div>
                    <a href="{{ route('agency.packages.create', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95f42]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        {{ __('messages.create_package') }}
                    </a>
                </div>

                <div class="mt-9 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.travel_packages') }}</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#fff1ed] text-[#e76f51]">●</span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $packageCount }}</p>
                        <p class="mt-1 text-xs text-[#607985]">Saved package inventory</p>
                    </div>
                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.published') }}</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#edf4f3] text-[#2f9e7a]">✓</span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $publishedPackageCount }}</p>
                        <p class="mt-1 text-xs text-[#607985]">Live to travelers</p>
                    </div>
                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-5">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.draft') }}</p>
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#fff8e8] text-[#c58b27]">•</span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $draftPackageCount }}</p>
                        <p class="mt-1 text-xs text-[#607985]">Work in progress</p>
                    </div>
                </div>

                <div class="mt-10 grid gap-8 xl:grid-cols-[1.3fr_.7fr]">
                    <section class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.recent_activity') }}</p>
                                <h3 class="mt-2 text-2xl font-bold">{{ __('messages.travel_packages') }}</h3>
                            </div>
                            <a href="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" class="text-sm font-bold text-[#e76f51]">{{ __('messages.view_all') }}</a>
                        </div>

                        <div class="mt-6 space-y-3">
                            @forelse ($recentPackages as $package)
                                <a href="{{ route('agency.packages.edit', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="flex items-center justify-between gap-4 rounded-xl border border-[#e7eceb] p-4 transition hover:border-[#e76f51]">
                                    <div>
                                        <p class="text-sm font-bold">{{ $package->title }}</p>
                                        <p class="mt-1 text-xs text-[#607985]">{{ $package->destination }} · {{ $package->duration_days }} days</p>
                                    </div>
                                    <span class="rounded-full bg-[#f1f6f4] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em] text-[#173042]">{{ $package->status }}</span>
                                </a>
                            @empty
                                <div class="rounded-xl border border-dashed border-[#cbd9d8] bg-[#f8faf9] p-6 text-center text-sm text-[#607985]">
                                    {{ __('messages.no_packages_yet') }}
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.open_inquiries') }}</p>
                        <div class="mt-5 space-y-3">
                            @forelse ($recentInquiries as $inquiry)
                                <div class="rounded-xl bg-[#f7f8f6] p-3">
                                    <p class="text-sm font-bold">{{ $inquiry->subject }}</p>
                                    <p class="mt-1 text-xs text-[#607985]">{{ $inquiry->user->name }}</p>
                                </div>
                            @empty
                                <p class="text-sm text-[#607985]">{{ __('messages.no_inquiries') }}</p>
                            @endforelse
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script>
        const agencyShell = document.querySelector('[data-agency-shell]');
        const agencySidebar = document.querySelector('[data-agency-sidebar]');
        const agencyBackdrop = document.querySelector('[data-agency-backdrop]');
        const setAgencyMenu = (open) => {
            agencySidebar?.classList.toggle('-translate-x-full', !open);
            agencySidebar?.classList.toggle('translate-x-0', open);
            agencyBackdrop?.classList.toggle('hidden', !open);
            agencyBackdrop?.classList.toggle('opacity-0', !open);
            agencyBackdrop?.classList.toggle('opacity-100', open);
            document.body.classList.toggle('overflow-hidden', open);
        };
        document.querySelector('[data-agency-open]')?.addEventListener('click', () => setAgencyMenu(true));
        document.querySelector('[data-agency-close]')?.addEventListener('click', () => setAgencyMenu(false));
        agencyBackdrop?.addEventListener('click', () => setAgencyMenu(false));
        agencySidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setAgencyMenu(false)));
    </script>
</x-auth-layout>
