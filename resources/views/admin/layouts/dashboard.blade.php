<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.admin_dashboard')) | TravelConnect</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    <div
        class="admin-dashboard min-h-screen"
        data-admin-shell
        data-admin-theme="light"
        data-search-empty="{{ __('messages.admin_nav.search_empty') }}"
    >
        <a href="#admin-main" class="admin-skip-link sr-only focus:not-sr-only">{{ __('messages.admin_nav.skip_to_content') }}</a>

        <header class="admin-header sticky top-0 z-50 border-b">
            <div class="flex min-h-16 flex-wrap items-center gap-3 px-3 py-2 sm:px-5 lg:flex-nowrap lg:px-7">
                <div class="flex min-w-0 flex-1 items-center gap-3 lg:flex-none">
                    <button
                        type="button"
                        class="admin-icon-button flex h-9 w-9 shrink-0 items-center justify-center rounded-lg lg:hidden"
                        data-admin-sidebar-toggle
                        aria-controls="admin-sidebar"
                        aria-expanded="false"
                        aria-label="{{ __('messages.admin_nav.open_sidebar') }}"
                    >
                        <x-admin.icon name="menu" class="h-5 w-5" />
                    </button>
                    <a href="{{ route('admin.dashboard', ['lang' => app()->getLocale()]) }}" class="flex min-w-0 items-center gap-2.5 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2">
                        <span class="admin-brand-mark flex h-9 w-9 shrink-0 items-center justify-center rounded-xl">
                            <x-admin.icon name="globe" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-extrabold tracking-tight">TravelConnect</span>
                            <span class="hidden text-[10px] font-bold uppercase tracking-[.14em] admin-muted sm:block">{{ __('messages.admin_nav.super_admin') }}</span>
                        </span>
                    </a>
                </div>

                <form class="relative order-last w-full sm:order-none sm:flex-1 lg:mx-8 lg:max-w-xl" role="search" data-admin-search-form>
                    <label for="admin-navigation-search" class="sr-only">{{ __('messages.admin_nav.search_navigation') }}</label>
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center admin-muted">
                        <x-admin.icon name="search" class="h-4 w-4" />
                    </span>
                    <input
                        id="admin-navigation-search"
                        type="search"
                        autocomplete="off"
                        placeholder="{{ __('messages.admin_nav.search_navigation') }}"
                        class="admin-input h-10 w-full rounded-lg border pl-9 pr-3 text-sm"
                        aria-controls="admin-search-results"
                        aria-expanded="false"
                        data-admin-search
                    >
                    <div id="admin-search-results" class="admin-popover absolute left-0 right-0 top-full z-50 mt-2 hidden max-h-80 overflow-y-auto rounded-xl border p-2 shadow-xl" data-admin-search-results></div>
                </form>

                <div class="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2 lg:ml-0">
                    <div class="admin-language-switch flex h-9 items-center gap-0.5 rounded-lg border p-0.5" role="group" aria-label="{{ __('messages.admin_nav.select_language') }}">
                        <button
                            type="button"
                            lang="en"
                            aria-label="{{ __('messages.admin_nav.switch_language_to_english') }}"
                            aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}"
                            class="admin-language-button inline-flex h-7 min-w-10 items-center justify-center gap-1 rounded-md px-1.5 text-xs font-bold sm:px-2"
                            data-admin-locale="en"
                        >
                            <x-language-flag locale="en" />
                            <span>EN</span>
                        </button>
                        <button
                            type="button"
                            lang="fr"
                            aria-label="{{ __('messages.admin_nav.switch_language_to_french') }}"
                            aria-pressed="{{ app()->getLocale() === 'fr' ? 'true' : 'false' }}"
                            class="admin-language-button inline-flex h-7 min-w-10 items-center justify-center gap-1 rounded-md px-1.5 text-xs font-bold sm:px-2"
                            data-admin-locale="fr"
                        >
                            <x-language-flag locale="fr" />
                            <span>FR</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="admin-icon-button flex h-9 w-9 items-center justify-center rounded-lg"
                        aria-label="{{ __('messages.toggle_theme') }}"
                        aria-pressed="false"
                        data-admin-theme-toggle
                    >
                        <x-admin.icon name="sun" class="h-4.5 w-4.5" data-theme-light-icon />
                        <x-admin.icon name="moon" class="hidden h-4.5 w-4.5" data-theme-dark-icon />
                    </button>

                    <div class="relative">
                        <button
                            type="button"
                            class="admin-icon-button relative flex h-9 w-9 items-center justify-center rounded-lg {{ $unreadAdminSecurityNotificationCount > 0 ? 'has-security-alert' : '' }}"
                            aria-label="{{ $unreadAdminSecurityNotificationCount > 0 ? __('messages.admin_nav.urgent_admin_security_alert') : __('messages.admin_nav.notifications') }}"
                            aria-controls="admin-notifications"
                            aria-expanded="false"
                            data-admin-popover-toggle="admin-notifications"
                        >
                            <x-admin.icon name="bell" class="h-4.5 w-4.5" />
                            @if ($unreadAdminSecurityNotificationCount > 0)
                                <x-admin.icon name="alert_triangle" class="admin-security-alert-indicator absolute -right-1 -top-1 h-4 w-4" />
                            @endif
                            @if ($unreadAdminNotificationCount > 0)
                                <span data-admin-notification-count="{{ $unreadAdminNotificationCount }}" class="admin-notification-count absolute -right-1.5 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-bold" aria-label="{{ __('messages.admin_nav.unread_notification_count', ['count' => $unreadAdminNotificationCount]) }}">
                                    {{ $unreadAdminNotificationCount > 99 ? '99+' : $unreadAdminNotificationCount }}
                                </span>
                            @endif
                        </button>
                        <section id="admin-notifications" class="admin-popover absolute right-0 top-full z-50 mt-2 hidden w-[min(22rem,calc(100vw-1.5rem))] rounded-xl border p-3 shadow-xl sm:p-4" aria-label="{{ __('messages.admin_nav.notifications') }}" data-admin-popover>
                            <div class="flex items-center justify-between gap-3 border-b pb-3 admin-border">
                                <div class="grid gap-1">
                                    <h2 class="text-sm font-bold admin-heading">{{ __('messages.admin_nav.notifications') }}</h2>
                                    <p class="text-xs admin-muted">{{ __('messages.admin_nav.unread_notification_count', ['count' => $unreadAdminNotificationCount]) }}</p>
                                </div>
                                @if ($unreadAdminNotificationCount > 0)
                                    <form action="{{ route('admin.notifications.read-all', ['lang' => app()->getLocale()]) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="admin-notification-mark-read text-xs font-semibold">{{ __('messages.admin_nav.mark_all_read') }}</button>
                                    </form>
                                @endif
                            </div>
                            @forelse ($adminNotifications as $notification)
                                @php
                                    $notificationData = $notification->data;
                                    $isSecurityNotification = isset($notificationData['kind']);
                                    $isAgencyDocumentNotification = $notification->type === \App\Notifications\AdminAgencyDocumentReceived::class;
                                @endphp
                                <a href="{{ route('admin.notifications.open', ['notification' => $notification->id, 'lang' => app()->getLocale()]) }}" data-admin-notification-type="{{ $isSecurityNotification ? 'security' : ($isAgencyDocumentNotification ? 'agency_document' : 'traveler_issue') }}" class="admin-notification-item flex items-start gap-3 border-b px-2 py-3 last:border-b-0 admin-border {{ is_null($notification->read_at) ? 'is-unread' : '' }} {{ $isSecurityNotification ? 'is-security-alert' : '' }}">
                                    <span class="admin-notification-icon mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg">
                                        <x-admin.icon :name="$isSecurityNotification ? 'alert_triangle' : ($isAgencyDocumentNotification ? 'file' : 'flag')" class="h-4 w-4" />
                                    </span>
                                    <span class="grid min-w-0 flex-1 gap-1">
                                        <span class="flex items-center justify-between gap-2">
                                            <span class="text-xs font-bold admin-heading">{{ $isSecurityNotification ? __('messages.admin_nav.security_notification_title') : ($isAgencyDocumentNotification ? __('messages.admin_nav.agency_document_notification_title') : __('messages.admin_nav.traveler_issue_notification_title', ['type' => __('messages.admin_alerts.categories.'.($notificationData['issue_type'] ?? 'complaint'))])) }}</span>
                                            @if ($isSecurityNotification)
                                                <span class="admin-security-alert-label shrink-0 rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide">{{ __('messages.admin_nav.urgent') }}</span>
                                            @endif
                                            @if (is_null($notification->read_at))
                                                <span class="admin-notification-unread-dot h-2 w-2 shrink-0 rounded-full" aria-label="{{ __('messages.admin_nav.unread') }}"></span>
                                            @endif
                                        </span>
                                        @if ($isSecurityNotification)
                                            <span class="break-words text-xs font-semibold leading-5 admin-security-alert-text">{{ __('messages.admin_nav.security_notification_messages.'.($notificationData['kind'] ?? 'unknown_device_password')) }}</span>
                                        @elseif ($isAgencyDocumentNotification)
                                            <span class="break-words text-xs leading-5 admin-muted">{{ __('messages.admin_nav.agency_document_notification_body', ['agency' => $notificationData['agency_name'] ?? '', 'type' => __('messages.admin_nav.agency_document_types.'.($notificationData['document_type'] ?? 'other'))]) }}</span>
                                        @else
                                            <span class="break-words text-xs leading-5 admin-muted">{{ $notificationData['subject'] ?? '' }}</span>
                                        @endif
                                        <span class="text-[10px] admin-muted">{{ $notification->created_at->locale(app()->getLocale())->diffForHumans() }} · {{ $isAgencyDocumentNotification ? __('messages.admin_nav.view_agency_profile') : __('messages.admin_nav.view_alert') }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="grid justify-items-center gap-3 py-5 text-center">
                                    <span class="admin-empty-icon flex h-10 w-10 items-center justify-center rounded-xl">
                                        <x-admin.icon name="inbox" class="h-5 w-5" />
                                    </span>
                                    <div class="grid gap-1">
                                        <h3 class="text-sm font-bold admin-heading">{{ __('messages.admin_nav.notifications_empty') }}</h3>
                                        <p class="text-xs leading-5 admin-muted">{{ __('messages.admin_nav.notifications_empty_description') }}</p>
                                    </div>
                                </div>
                            @endforelse
                        </section>
                    </div>

                    <div class="relative">
                        <button
                            type="button"
                            class="admin-profile-button flex h-9 items-center gap-2 rounded-lg px-1.5 sm:px-2"
                            aria-label="{{ __('messages.admin_nav.profile_menu') }}"
                            aria-controls="admin-profile-menu"
                            aria-expanded="false"
                            data-admin-popover-toggle="admin-profile-menu"
                        >
                            <span class="admin-avatar flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold">{{ mb_substr($admin->name, 0, 1) }}</span>
                            <span class="hidden max-w-32 truncate text-xs font-semibold sm:block">{{ $admin->name }}</span>
                            <x-admin.icon name="chevron_down" class="hidden h-3.5 w-3.5 admin-muted sm:block" />
                        </button>
                        <section id="admin-profile-menu" class="admin-popover absolute right-0 top-full z-50 mt-2 hidden w-64 rounded-xl border p-3 shadow-xl" aria-label="{{ __('messages.admin_nav.profile_menu') }}" data-admin-popover>
                            <div class="grid gap-1 border-b px-2 pb-3 admin-border">
                                <p class="truncate text-sm font-bold admin-heading">{{ $admin->name }}</p>
                                <p class="truncate text-xs admin-muted">{{ $admin->email }}</p>
                            </div>
                            <form action="{{ route('admin.logout', ['lang' => app()->getLocale()]) }}" method="POST" class="pt-2">
                                @csrf
                                <button type="submit" class="admin-menu-action flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-semibold">
                                    <x-admin.icon name="logout" class="h-4 w-4" />
                                    {{ __('messages.logout') }}
                                </button>
                            </form>
                        </section>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-backdrop fixed inset-0 z-30 hidden lg:hidden" data-admin-backdrop></div>

        <div class="lg:grid lg:min-h-[calc(100vh-4rem)] lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside id="admin-sidebar" class="admin-sidebar fixed bottom-24 left-0 top-28 z-40 hidden w-[min(18rem,calc(100vw-2rem))] overflow-y-auto border-r p-4 lg:fixed lg:inset-y-0 lg:left-0 lg:z-40 lg:block lg:h-dvh lg:w-[17rem] lg:overflow-y-auto lg:px-4 lg:pb-24 lg:pt-20">
                <div class="mb-4 flex items-center justify-between px-2 lg:hidden">
                    <p class="text-xs font-bold uppercase tracking-[.14em] admin-muted">{{ __('messages.admin_nav.navigation') }}</p>
                    <button type="button" class="admin-icon-button flex h-8 w-8 items-center justify-center rounded-lg" data-admin-sidebar-close aria-label="{{ __('messages.admin_nav.close_sidebar') }}">
                        <x-admin.icon name="close" class="h-4 w-4" />
                    </button>
                </div>
                <p class="mb-3 px-2 text-[10px] font-bold uppercase tracking-[.16em] admin-muted">{{ __('messages.admin_nav.sections.'.$activeSection) }}</p>
                <nav aria-label="{{ __('messages.admin_nav.sidebar_label') }}" class="grid content-start gap-1">
                    @foreach ($sections[$activeSection]['groups'] as $group)
                        @php
                            $groupPages = $group['pages'] ?? [$group['page']];
                            $groupIsActive = $activePage !== null && in_array($activePage, $groupPages, true);
                        @endphp
                        @if (isset($group['page']))
                            @php
                                $groupUrl = $group['page'] === 'overview'
                                    ? route('admin.dashboard', ['lang' => app()->getLocale()])
                                    : route('admin.dashboard.page', ['page' => $group['page'], 'lang' => app()->getLocale()]);
                            @endphp
                            <a href="{{ $groupUrl }}" @if ($activePage === $group['page']) aria-current="page" @endif class="admin-nav-link flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold {{ $groupIsActive ? 'is-active' : '' }}">
                                <x-admin.icon :name="$group['icon']" class="h-4.5 w-4.5 shrink-0" />
                                <span>{{ __('messages.admin_nav.pages.'.$group['page']) }}</span>
                            </a>
                        @else
                            <details class="admin-nav-group rounded-lg" @if ($groupIsActive) open @endif>
                                <summary class="admin-nav-link flex min-h-10 cursor-pointer list-none items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold {{ $groupIsActive ? 'is-active' : '' }}">
                                    <x-admin.icon :name="$group['icon']" class="h-4.5 w-4.5 shrink-0" />
                                    <span class="min-w-0 flex-1">{{ __('messages.admin_nav.groups.'.$group['label']) }}</span>
                                    <x-admin.icon name="chevron_down" class="admin-nav-chevron h-3.5 w-3.5 shrink-0 transition-transform" />
                                </summary>
                                <ul class="ml-5 mt-1 grid gap-0.5 border-l pl-3 admin-border">
                                    @foreach ($group['pages'] as $childPage)
                                        <li>
                                            <a href="{{ route('admin.dashboard.page', ['page' => $childPage, 'lang' => app()->getLocale()]) }}" @if ($activePage === $childPage) aria-current="page" @endif class="admin-nav-link admin-child-link flex min-h-9 items-center rounded-md px-3 py-2 text-[13px] {{ $activePage === $childPage ? 'is-active' : '' }}">
                                                {{ __('messages.admin_nav.pages.'.$childPage) }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    @endforeach
                </nav>
                <div class="mt-8 border-t px-2 pt-4 admin-border">
                    <p class="text-xs font-semibold admin-muted">{{ __('messages.admin_nav.super_admin_workspace') }}</p>
                    <p class="mt-1 text-[11px] admin-muted">{{ $admin->email }}</p>
                </div>
            </aside>

            <main id="admin-main" class="min-w-0 px-4 py-7 pb-36 sm:px-6 lg:col-start-2 lg:px-9 lg:py-9 lg:pb-32" tabindex="-1">
                @yield('dashboard-content')
            </main>
        </div>

        <nav class="admin-bottom-nav fixed inset-x-0 bottom-0 z-50 border-t" aria-label="{{ __('messages.admin_nav.main_navigation') }}">
            <div class="admin-bottom-scroll">
                <div class="mx-auto grid w-full max-w-5xl grid-cols-6 items-stretch gap-1 px-2 py-2 pb-[max(.5rem,env(safe-area-inset-bottom))] sm:px-4">
                    @foreach ($sections as $sectionKey => $section)
                        @php
                            $sectionUrl = $sectionKey === 'main'
                                ? route('admin.dashboard', ['lang' => app()->getLocale()])
                                : route('admin.dashboard.section', ['section' => $sectionKey, 'lang' => app()->getLocale()]);
                        @endphp
                        <a
                            href="{{ $sectionUrl }}"
                            aria-label="{{ __('messages.admin_nav.sections.'.$sectionKey) }}"
                            data-tooltip="{{ __('messages.admin_nav.sections.'.$sectionKey) }}"
                            @if ($activeSection === $sectionKey) aria-current="page" @endif
                            class="admin-section-link relative flex min-h-11 min-w-0 flex-col items-center justify-center gap-1 rounded-lg px-1 py-1.5 text-center {{ $activeSection === $sectionKey ? 'is-active' : '' }}"
                        >
                            <x-admin.icon :name="$section['icon']" class="h-5 w-5" />
                            <span class="admin-section-label whitespace-nowrap text-[11px] font-semibold sm:text-xs">{{ __('messages.admin_nav.sections.'.$sectionKey) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>

        <div class="hidden" aria-hidden="true" data-admin-search-index>
            @foreach ($navigationSearchItems as $searchItem)
                <a href="{{ $searchItem['url'] }}">{{ $searchItem['label'] }}</a>
            @endforeach
        </div>
    </div>
</body>
</html>
