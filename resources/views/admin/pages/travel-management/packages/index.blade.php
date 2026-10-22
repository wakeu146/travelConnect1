@php
    $packagePages = [
        'all_travel_packages',
        'published_packages',
        'draft_unpublished_packages',
        'reported_pending_review',
    ];
@endphp

<section class="grid gap-6" data-admin-package-directory>
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.package_introduction') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_travel.package_result_count', ['count' => $packages->total()]) }}</p>
    </header>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('messages.admin_travel.package_tabs') }}">
        @foreach ($packagePages as $packagePage)
            <a href="{{ route('admin.dashboard.page', ['page' => $packagePage, 'lang' => app()->getLocale()]) }}"
                @if ($activePage === $packagePage) aria-current="page" @endif
                class="admin-nav-link inline-flex min-h-10 items-center border px-3 py-2 text-sm font-semibold {{ $activePage === $packagePage ? 'is-active' : '' }}">
                {{ __('messages.admin_nav.pages.'.$packagePage) }}
            </a>
        @endforeach
    </nav>

    @if ($activePage === 'reported_pending_review' && ! $reportsAvailable)
        <div role="status" class="border border-amber-500/40 bg-amber-500/10 p-4 text-sm leading-6 text-amber-900 dark:text-amber-200" data-package-reports-unavailable>
            {{ __('messages.admin_travel.reports_unavailable') }}
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => $activePage, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="grid min-w-0 gap-1.5 sm:col-span-2">
            <label for="package-search" class="text-xs font-semibold admin-heading">{{ __('messages.admin_travel.search_packages') }}</label>
            <input id="package-search" name="search" value="{{ $filters['search'] ?? '' }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.admin_travel.package_search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="package-status" class="text-xs font-semibold admin-heading">{{ __('messages.admin_travel.publication_status') }}</label>
            <select id="package-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>{{ __('messages.admin_travel.all_statuses') }}</option>
                <option value="unpublished" @selected(($filters['status'] ?? '') === 'unpublished')>{{ __('messages.admin_nav.pages.draft_unpublished_packages') }}</option>
                @foreach (['draft', 'published', 'archived'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? 'all') === $status)>{{ __('messages.admin_travel.statuses.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="package-destination" class="text-xs font-semibold admin-heading">{{ __('messages.admin_travel.destination') }}</label>
            <select id="package-destination" name="destination" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="">{{ __('messages.admin_travel.all_destinations') }}</option>
                @foreach ($destinations as $destination)
                    <option value="{{ $destination }}" @selected(($filters['destination'] ?? '') === $destination)>{{ $destination }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex min-w-0 flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.admin_travel.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => $activePage, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.clear_filters') }}</a>
        </div>
    </form>

    @if ($activePage !== 'reported_pending_review' || $reportsAvailable)
        @if ($packages->isNotEmpty())
            <div class="admin-empty-state overflow-x-auto border">
                <table class="w-full min-w-[64rem] text-left text-sm">
                    <thead class="border-b admin-border">
                        <tr class="text-xs uppercase tracking-wide admin-muted">
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.package') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.agency') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.destination') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.price') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.publication_status') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.availability') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">{{ __('messages.admin_travel.open_package') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y admin-border">
                        @foreach ($packages as $package)
                            <tr class="align-top">
                                <td class="px-4 py-4">
                                    <a href="{{ route('admin.packages.show', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="font-semibold admin-heading underline decoration-slate-300 underline-offset-4">{{ $package->title }}</a>
                                    <p class="mt-1 text-xs admin-muted">{{ __('messages.admin_travel.days_count', ['count' => $package->duration_days]) }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold admin-heading">{{ $package->agency?->company_name ?? __('messages.not_available') }}</p>
                                    <p class="mt-1 text-xs admin-muted">{{ $package->agency?->owner?->name ?? __('messages.not_available') }}</p>
                                </td>
                                <td class="px-4 py-4">{{ $package->destination }}</td>
                                <td class="px-4 py-4 whitespace-nowrap">{{ __('messages.admin_travel.price_from', ['price' => number_format((float) $package->price_from, 2), 'currency' => config('travelconnect.currency')]) }}</td>
                                <td class="px-4 py-4"><span class="admin-badge rounded-full px-2.5 py-1 text-xs font-semibold">{{ __('messages.admin_travel.statuses.'.$package->status->value) }}</span></td>
                                <td class="px-4 py-4">{{ __('messages.admin_travel.availability_count', ['count' => $package->active_availabilities_count]) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('admin.packages.show', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.admin_travel.open_package') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $packages->links() }}
        @else
            <div class="admin-empty-state grid gap-2 border p-8 text-center">
                <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_travel.packages_empty_title') }}</h2>
                <p class="text-sm admin-muted">{{ __('messages.admin_travel.packages_empty_description') }}</p>
            </div>
        @endif
    @endif
</section>
