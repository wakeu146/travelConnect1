@props(['title' => null])

@php
    $routeName = request()->route()?->getName();
    $pageTitle = $title ?? match (true) {
        $routeName === 'agency.dashboard' => __('messages.agency_dashboard'),
        $routeName === 'agency.packages.create' => __('messages.create_package'),
        $routeName === 'agency.packages.edit' => __('messages.edit_package'),
        $routeName === 'agency.packages.index' => __('messages.manage_travel_packages'),
        $routeName === 'agency.availability.create' => __('messages.create_departure'),
        $routeName === 'agency.availability.edit' => __('messages.edit_departure'),
        $routeName === 'agency.availability.index' => __('messages.manage_departures'),
        $routeName === 'agency.bookings.show' => __('messages.booking_details'),
        $routeName === 'agency.bookings.index' => __('messages.booking_management'),
        $routeName === 'agency.inquiries.index' => __('messages.inquiries'),
        $routeName === 'agency.customers.index' => __('messages.customers'),
        $routeName === 'agency.reviews.index' => __('messages.review_management'),
        $routeName === 'agency.payments.index' => __('messages.booking_payments'),
        $routeName === 'agency.analytics.index' => __('messages.business_analytics'),
        $routeName === 'agency.profile' || $routeName === 'agency.settings.update' => __('messages.agency_profile'),
        default => __('messages.agency_dashboard'),
    };
    $agency = $agency ?? auth()->user()?->agency;
    $brandName = $agency?->company_name ?? 'TravelConnect';
    $verificationStatus = $agency?->verification_status?->value ?? $agency?->verification_status;
    $verificationTone = match ($verificationStatus) {
        'verified' => 'success',
        'rejected' => 'danger',
        'pending' => 'warning',
        default => 'neutral',
    };
    $navItems = [
        ['route' => 'agency.dashboard', 'label' => __('messages.overview'), 'icon' => '<rect x="4" y="4" width="6" height="6" rx="1"></rect><rect x="14" y="4" width="6" height="6" rx="1"></rect><rect x="4" y="14" width="6" height="6" rx="1"></rect><rect x="14" y="14" width="6" height="6" rx="1"></rect>'],
        ['route' => 'agency.packages.index', 'active' => 'agency.packages.*', 'label' => __('messages.travel_packages'), 'icon' => '<path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Zm0 9 8 4.5 8-4.5M4 7.5V16l8 4.5V12"></path>'],
        ['route' => 'agency.availability.index', 'active' => 'agency.availability.*', 'label' => __('messages.availability'), 'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M8 3v4M16 3v4M3 10h18"></path>'],
        ['route' => 'agency.bookings.index', 'active' => 'agency.bookings.*', 'label' => __('messages.bookings'), 'icon' => '<path d="M7 7h10M7 12h10M7 17h6"></path><rect x="3" y="4" width="18" height="16" rx="2"></rect>'],
        ['route' => 'agency.inquiries.index', 'active' => 'agency.inquiries.*', 'label' => __('messages.inquiries'), 'icon' => '<path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-4 2v-6.5A7.5 7.5 0 1 1 20 11.5Z"></path><path d="M8 11h8M8 14h5"></path>'],
        ['route' => 'agency.customers.index', 'label' => __('messages.customers'), 'icon' => '<circle cx="9" cy="8" r="3"></circle><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v1"></path>'],
        ['route' => 'agency.reviews.index', 'label' => __('messages.reviews'), 'icon' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"></path>'],
        ['route' => 'agency.payments.index', 'label' => __('messages.payments'), 'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 15h4"></path>'],
        ['route' => 'agency.analytics.index', 'label' => __('messages.analytics'), 'icon' => '<path d="M4 19V5M4 19h17M8 15l3-4 3 2 5-7"></path>'],
        ['route' => 'agency.profile', 'active' => 'agency.profile', 'label' => __('messages.profile'), 'icon' => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.8 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 8.92 4.8a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.25.6.84 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>'],
    ];
@endphp

<x-auth-layout :title="$pageTitle . ' | TravelConnect'">
    <div data-agency-shell class="dashboard-shell min-h-screen bg-[#f7f8f6] text-[#173042] transition-colors duration-300">
        <aside data-agency-sidebar class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col overflow-y-auto overscroll-contain border-r border-[#e4e9e7] bg-white px-5 py-6 shadow-xl transition-transform duration-300 lg:translate-x-0 lg:shadow-none">
        <div class="flex items-center justify-between gap-2 px-2">
            <div data-agency-brand class="flex min-w-0 items-center gap-3 text-lg font-semibold">
                <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-[#f1f6f4]">
                    <img src="{{ asset('images/logo.png') }}" alt="TravelConnect logo" class="h-full w-full object-contain">
                </span>
                <span>Travel<span class="font-normal text-[#e76f51]">Connect</span></span>
            </div>
            <button type="button" data-agency-expand class="hidden h-9 w-9 items-center justify-center rounded-lg text-[#607985] hover:bg-[#f1f6f4]" aria-label="{{ __('messages.expand_navigation') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="m9 5 6 7-6 7"/></svg></button>
            <button type="button" data-agency-collapse class="hidden h-9 w-9 items-center justify-center rounded-lg text-[#607985] hover:bg-[#f1f6f4] lg:flex" aria-label="{{ __('messages.collapse_navigation') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="m15 5-6 7 6 7"/></svg></button>
            <button type="button" data-agency-close class="flex h-9 w-9 items-center justify-center rounded-lg text-[#607985] hover:bg-[#f1f6f4] lg:hidden" aria-label="{{ __('messages.close_navigation') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <div class="mt-10">
            <p data-agency-label class="px-3 text-[10px] font-bold uppercase tracking-[.2em] text-[#9aadb2]">{{ __('messages.agency_workspace') }}</p>
            <nav class="mt-3 grid gap-1" aria-label="{{ __('messages.dashboard_navigation') }}">
                @foreach ($navItems as $item)
                    @php
                        $isActive = request()->routeIs($item['active'] ?? $item['route']);
                    @endphp
                    <a href="{{ route($item['route'], ['lang' => app()->getLocale()]) }}"
                       title="{{ $item['label'] }}"
                              class="agency-nav-link flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold transition {{ $isActive ? 'is-active' : '' }}"
                              @if ($isActive) aria-current="page" @endif
                       data-agency-nav-link
                       data-agency-nav-path="{{ parse_url(route($item['route'], ['lang' => app()->getLocale()]), PHP_URL_PATH) }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true">{!! $item['icon'] !!}</svg>
                        <span data-agency-label>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <div class="mt-auto border-t border-[#e4e9e7] pt-5">
            <div class="agency-user-card flex items-center gap-3 px-2">
                <span class="agency-user-avatar flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-[#173042] text-sm font-bold text-white">
                    @if ($agency?->logoUrl())
                        <img src="{{ $agency->logoUrl() }}" alt="{{ $brandName }}" class="h-full w-full object-cover">
                    @elseif (auth()->user()?->profile_photo_path)
                        <img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="{{ auth()->user()?->name ?? 'Agency owner' }}" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(substr((auth()->user()?->name ?? 'A'), 0, 1)) }}
                    @endif
                </span>
                <div class="agency-user-meta min-w-0">
                    <p data-agency-label class="truncate text-sm font-bold">{{ auth()->user()?->name ?? 'Agency owner' }}</p>
                    <p data-agency-label class="truncate text-xs text-[#607985]">{{ $brandName }}</p>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST" class="agency-sidebar-logout mt-4">@csrf<button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-[#607985] hover:bg-[#fff1ed] hover:text-[#e76f51]"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M10 5H5a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h5M13 8l4 4-4 4M8 12h9"/></svg><span data-agency-label>{{ __('messages.logout') }}</span></button></form>
        </div>
    </aside>

    <div data-agency-backdrop class="fixed inset-0 z-40 hidden bg-[#102936]/40 opacity-0 transition-opacity duration-300 lg:hidden"></div>

    <div data-agency-main class="lg:pl-72">
        <header data-agency-topbar class="sticky top-0 z-30 border-b border-[#e4e9e7] bg-white/95 px-5 py-4 backdrop-blur sm:px-8">
            <div class="flex flex-wrap items-center gap-3 lg:flex-nowrap lg:gap-4">
                <button type="button" data-agency-open class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#dbe3e5] text-[#173042] lg:hidden" aria-label="{{ __('messages.open_navigation') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                    <span class="agency-panel-muted flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-xl border text-sm font-bold sm:h-10 sm:w-10">
                        @if ($agency?->logoUrl())
                            <img src="{{ $agency->logoUrl() }}" alt="{{ __('messages.agency_logo_alt', ['name' => $brandName]) }}" class="h-full w-full object-cover">
                        @else
                            {{ str($brandName)->substr(0, 1)->upper() }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="agency-text-subtle hidden text-[10px] font-bold uppercase tracking-[.16em] sm:block">{{ __('messages.agency_workspace') }} · {{ auth()->user()?->name }}</p>
                        <div class="mt-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                            <h1 class="truncate text-sm font-bold text-[#173042] sm:text-lg">{{ $brandName }}</h1>
                            @if ($verificationStatus)
                                <span class="agency-pill agency-pill-{{ $verificationTone }} rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.1em]">{{ __('messages.agency_verification_status_'.$verificationStatus) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <form data-agency-search action="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" method="GET" class="relative order-last flex w-full min-w-0 lg:order-none lg:max-w-xl lg:flex-1">
                    <label for="agency-global-search" class="sr-only">{{ __('messages.search_packages') }}</label>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="agency-text-subtle pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16.5 16.5 4 4"></path></svg>
                    <input id="agency-global-search" type="search" name="search" value="{{ request('search') }}" placeholder="{{ __('messages.search_packages') }}" class="agency-input w-full rounded-xl border py-2.5 pl-10 pr-12 text-sm outline-none transition focus:ring-2 focus:ring-[#e76f51]/15">
                    <button data-agency-search-submit type="submit" class="agency-button-primary absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg" aria-label="{{ __('messages.search_packages') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16.5 16.5 4 4"></path></svg>
                    </button>
                </form>
                @php($notificationCount = auth()->user()?->unreadNotifications()->count() ?? 0)
                <div class="ml-auto flex shrink-0 items-center gap-2">
                <button type="button" data-agency-theme-top class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#dbe3e5] text-[#607985] hover:border-[#e76f51] hover:text-[#e76f51]" aria-label="{{ __('messages.toggle_theme') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M20.5 15.2A8.5 8.5 0 0 1 8.8 3.5 8.5 8.5 0 1 0 20.5 15.2Z"></path></svg>
                </button>
                <div data-agency-language-switcher class="flex shrink-0 items-center rounded-lg border border-[#dbe3e5] p-1" aria-label="{{ __('messages.language') }}">
                    <a data-lang-button href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" class="rounded-md px-2 py-1 text-[10px] font-bold {{ app()->getLocale() === 'en' ? 'bg-[#173042] text-white' : 'agency-text-subtle' }}">EN</a>
                    <a data-lang-button href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}" class="rounded-md px-2 py-1 text-[10px] font-bold {{ app()->getLocale() === 'fr' ? 'bg-[#173042] text-white' : 'agency-text-subtle' }}">FR</a>
                </div>
                <a href="{{ route('agency.notifications.index', ['lang' => app()->getLocale()]) }}" aria-label="{{ __('messages.notifications') }}" title="{{ __('messages.notifications') }}" class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#dbe3e5] bg-white text-[#607985] transition hover:border-[#e76f51] hover:text-[#e76f51]">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path><path d="M10 20a2 2 0 0 0 4 0"></path></svg>
                    @if ($notificationCount > 0)
                        <span class="absolute -right-1.5 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-[#e76f51] px-1 text-[10px] font-bold text-white">
                            {{ $notificationCount > 99 ? '99+' : $notificationCount }}
                        </span>
                    @endif
                </a>
                </div>
            </div>
        </header>

            <main id="agency-main-content" class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10 lg:py-10">
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
    (() => {
        const shell = document.querySelector('[data-agency-shell]');
        const sidebar = document.querySelector('[data-agency-sidebar]');
        const backdrop = document.querySelector('[data-agency-backdrop]');
        const pageLoader = document.getElementById('page-loader');
        const themeKey = 'travelconnect-dashboard-theme';
        const collapseKey = 'travelconnect-agency-collapsed';
        let agencyLoaderTimer = null;
        const setPageLoading = (loading) => {
            if (loading) {
                window.clearTimeout(agencyLoaderTimer);
                agencyLoaderTimer = window.setTimeout(() => {
                    pageLoader?.classList.remove('pointer-events-none', 'opacity-0');
                    document.getElementById('agency-main-content')?.setAttribute('aria-busy', 'true');
                }, 700);
                return;
            }

            window.clearTimeout(agencyLoaderTimer);
            pageLoader?.classList.add('pointer-events-none', 'opacity-0');
            document.getElementById('agency-main-content')?.setAttribute('aria-busy', 'false');
        };
        const getAgencyLocale = () => {
            const locale = new URL(window.location.href).searchParams.get('lang') || document.documentElement.lang.split('-')[0];
            return ['en', 'fr'].includes(locale) ? locale : 'en';
        };
        const syncAgencyLinkLocale = (locale = getAgencyLocale()) => {
            document.querySelectorAll('[data-agency-nav-link]').forEach((link) => {
                const linkUrl = new URL(link.href, window.location.href);
                if (linkUrl.origin !== window.location.origin) return;
                linkUrl.searchParams.set('lang', locale);
                link.href = linkUrl.toString();
            });
        };
        syncAgencyLinkLocale();
        document.querySelectorAll('[data-lang-button]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const nextLocale = new URL(button.href, window.location.href).searchParams.get('lang');
                if (!['en', 'fr'].includes(nextLocale)) return;

                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('lang', nextLocale);
                syncAgencyLinkLocale(nextLocale);
                window.location.assign(nextUrl.toString());
            });
        });
        const setCollapsed = (collapsed) => {
            shell?.classList.toggle('agency-collapsed', collapsed);
            localStorage.setItem(collapseKey, collapsed ? 'true' : 'false');
        };
        setCollapsed(localStorage.getItem(collapseKey) === 'true');
        document.querySelector('[data-agency-collapse]')?.addEventListener('click', () => setCollapsed(true));
        document.querySelector('[data-agency-expand]')?.addEventListener('click', () => setCollapsed(false));

        const applyAgencyTheme = (dark) => {
            shell?.classList.toggle('dashboard-dark', dark);
            document.body.classList.toggle('dashboard-dark', dark);
            document.querySelectorAll('[data-agency-theme-top]').forEach((button) => {
                const icon = button.querySelector('svg');
                if (!icon) return;
                icon.innerHTML = dark
                    ? '<path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"></path><circle cx="12" cy="12" r="3"></circle>'
                    : '<path d="M20.5 15.2A8.5 8.5 0 0 1 8.8 3.5 8.5 8.5 0 1 0 20.5 15.2Z"></path>';
            });
        };

        const savedTheme = localStorage.getItem(themeKey) === 'dark';
        applyAgencyTheme(savedTheme);

        document.querySelectorAll('[data-agency-theme-top]').forEach((button) => {
            button.addEventListener('click', () => {
                const dark = !shell.classList.contains('dashboard-dark');
                localStorage.setItem(themeKey, dark ? 'dark' : 'light');
                applyAgencyTheme(dark);
            });
        });

        const setSidebar = (open) => {
            sidebar?.classList.toggle('-translate-x-full', !open);
            sidebar?.classList.toggle('translate-x-0', open);
            backdrop?.classList.toggle('hidden', !open);
            backdrop?.classList.toggle('opacity-0', !open);
            backdrop?.classList.toggle('opacity-100', open);
            document.body.classList.toggle('overflow-hidden', open);
        };

        document.querySelector('[data-agency-open]')?.addEventListener('click', () => setSidebar(true));
        document.querySelector('[data-agency-close]')?.addEventListener('click', () => setSidebar(false));
        backdrop?.addEventListener('click', () => setSidebar(false));
        sidebar?.querySelectorAll('[data-agency-nav-link]').forEach((link) => {
            link.addEventListener('click', () => setSidebar(false));
        });
        document.querySelector('[data-agency-search]')?.addEventListener('submit', () => setPageLoading(true));

        const replaceAgencyMainContent = (htmlText, nextUrl) => {
            const main = document.getElementById('agency-main-content');
            if (!main) return false;
            const parser = new DOMParser();
            const nextDocument = parser.parseFromString(htmlText, 'text/html');
            const nextMain = nextDocument.getElementById('agency-main-content');
            if (!nextMain) return false;
            main.innerHTML = nextMain.innerHTML;
            const nextTitle = nextDocument.title || 'TravelConnect';
            if (nextTitle) document.title = nextTitle;
            if (nextUrl) history.pushState({}, '', nextUrl);
            return true;
        };

        const updateAgencyLinks = () => {
            document.querySelectorAll('[data-agency-nav-link]').forEach((link) => {
                const itemPath = link.dataset.agencyNavPath || new URL(link.href, window.location.href).pathname;
                const currentPath = window.location.pathname.replace(/\/$/, '');
                const normalizedItemPath = itemPath.replace(/\/$/, '');
                const isActive = currentPath === normalizedItemPath || currentPath.startsWith(`${normalizedItemPath}/`);
                link.classList.toggle('is-active', isActive);
                if (isActive) link.setAttribute('aria-current', 'page');
                else link.removeAttribute('aria-current');
            });
        };

        const handleAgencyFetch = (event) => {
            const link = event.currentTarget;
            if (!link || link.target === '_blank') return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
            syncAgencyLinkLocale();
            const absoluteHref = new URL(href, window.location.href);
            if (absoluteHref.origin !== window.location.origin) return;
            const isAgencyPath = absoluteHref.pathname.startsWith('/agency');
            if (!isAgencyPath) return;
            absoluteHref.searchParams.set('lang', getAgencyLocale());
            event.preventDefault();
            let navigatingAway = false;
            setPageLoading(true);
            fetch(absoluteHref, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((response) => {
                    if (!response.ok) {
                        navigatingAway = true;
                        window.location.href = absoluteHref.toString();
                        return null;
                    }
                    return response.text();
                })
                .then((html) => {
                    if (!html) return;
                    const ok = replaceAgencyMainContent(html, absoluteHref.toString());
                    if (!ok) {
                        navigatingAway = true;
                        window.location.href = absoluteHref.toString();
                        return;
                    }
                    updateAgencyLinks();
                })
                .catch(() => {
                    navigatingAway = true;
                    window.location.href = absoluteHref.toString();
                })
                .finally(() => {
                    if (!navigatingAway) setPageLoading(false);
                });
        };

        document.querySelectorAll('[data-agency-nav-link]').forEach((link) => {
            link.addEventListener('click', handleAgencyFetch);
        });

        window.addEventListener('popstate', () => {
            const href = new URL(window.location.href);
            if (href.pathname.startsWith('/agency')) {
                let navigatingAway = false;
                setPageLoading(true);
                fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((response) => {
                        if (!response.ok) throw new Error('Agency navigation failed');
                        return response.text();
                    })
                    .then((html) => replaceAgencyMainContent(html, window.location.href))
                    .catch(() => {
                        navigatingAway = true;
                        window.location.reload();
                    })
                    .finally(() => {
                        if (!navigatingAway) setPageLoading(false);
                    });
            }
        });

        updateAgencyLinks();
    })();
    </script>
</x-auth-layout>
