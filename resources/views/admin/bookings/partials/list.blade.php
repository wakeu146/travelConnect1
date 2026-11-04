@php
    $currentRoute = config("admin_dashboard.page_routes.{$page}");
    $tabs = [
        'all_bookings',
        'pending_bookings',
        'confirmed_bookings',
        'completed_bookings',
        'cancelled_rejected_bookings',
        'expired_bookings',
        'booking_disputes',
    ];
    $statusClasses = [
        'pending' => 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
        'confirmed' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
        'completed' => 'border-sky-500/40 bg-sky-500/10 text-sky-800 dark:text-sky-200',
        'cancelled' => 'border-slate-500/40 bg-slate-500/10 text-slate-700 dark:text-slate-200',
        'rejected' => 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200',
        'expired' => 'border-orange-500/40 bg-orange-500/10 text-orange-800 dark:text-orange-200',
    ];
    $statusRoutes = [
        'pending' => 'pending_bookings',
        'confirmed' => 'confirmed_bookings',
        'completed' => 'completed_bookings',
        'cancelled' => 'cancelled_rejected_bookings',
        'rejected' => 'cancelled_rejected_bookings',
        'expired' => 'expired_bookings',
    ];
    $exportUrl = route('admin.bookings.export', [
        'lang' => app()->getLocale(),
        'page' => $page,
        ...$filters,
    ]);
@endphp

<section class="grid min-w-0 gap-6" data-admin-bookings="{{ $page }}">
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_bookings.description') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_bookings.results', ['from' => $bookings->firstItem() ?? 0, 'to' => $bookings->lastItem() ?? 0, 'total' => $bookings->total()]) }}</p>
    </header>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('messages.admin_bookings.tabs') }}">
        @foreach ($tabs as $tab)
            @php
                $tabRoute = config("admin_dashboard.page_routes.{$tab}");
            @endphp
            <a href="{{ route($tabRoute, ['lang' => app()->getLocale()]) }}"
                @if ($page === $tab) aria-current="page" @endif
                class="admin-nav-link inline-flex min-h-10 items-center border px-3 py-2 text-sm font-semibold {{ $page === $tab ? 'is-active' : '' }}">
                {{ __('messages.admin_nav.pages.'.$tab) }}
            </a>
        @endforeach
    </nav>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ session('error') }}</p>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="{{ __('messages.admin_bookings.summary') }}">
        @foreach ([
            ['key' => 'total', 'page' => 'all_bookings'],
            ['key' => 'pending', 'page' => 'pending_bookings'],
            ['key' => 'confirmed', 'page' => 'confirmed_bookings'],
            ['key' => 'completed', 'page' => 'completed_bookings'],
            ['key' => 'cancelled', 'page' => 'cancelled_rejected_bookings'],
            ['key' => 'rejected', 'page' => 'cancelled_rejected_bookings'],
            ['key' => 'expired', 'page' => 'expired_bookings'],
            ['key' => 'open_disputes', 'page' => 'booking_disputes'],
        ] as $item)
            <a href="{{ route(config("admin_dashboard.page_routes.{$item['page']}"), ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-1 border p-4 focus-visible:outline-2 focus-visible:outline-offset-2">
                <span class="text-xs admin-muted">{{ __('messages.admin_bookings.summary_labels.'.$item['key']) }}</span>
                <span class="text-2xl font-bold admin-heading">{{ $summary[$item['key']] ?? 0 }}</span>
            </a>
        @endforeach
    </section>

    <form method="GET" action="{{ route($currentRoute, ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="grid min-w-0 gap-1.5 sm:col-span-2">
            <label for="booking-search" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.search') }}</label>
            <input id="booking-search" name="search" type="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.admin_bookings.search_placeholder') }}">
        </div>
        @if (in_array($page, ['all_bookings', 'cancelled_rejected_bookings'], true))
            <div class="grid min-w-0 gap-1.5">
                <label for="booking-status-filter" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.status') }}</label>
                <select id="booking-status-filter" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                    <option value="">{{ __('messages.admin_bookings.all_statuses') }}</option>
                    @foreach (in_array($page, ['cancelled_rejected_bookings'], true) ? ['cancelled', 'rejected'] : ['pending', 'confirmed', 'completed', 'cancelled', 'rejected', 'expired'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('messages.admin_bookings.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-payment-filter" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.payment_status') }}</label>
            <select id="booking-payment-filter" name="payment_status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="">{{ __('messages.admin_bookings.all_payment_statuses') }}</option>
                @foreach (['unpaid', 'partial', 'paid', 'refunded'] as $paymentStatus)
                    <option value="{{ $paymentStatus }}" @selected(($filters['payment_status'] ?? '') === $paymentStatus)>{{ __('messages.admin_bookings.payment_statuses.'.$paymentStatus) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-agency-filter" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.agency') }}</label>
            <input id="booking-agency-filter" name="agency" type="search" maxlength="160" value="{{ $filters['agency'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-package-filter" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.package') }}</label>
            <input id="booking-package-filter" name="package" type="search" maxlength="191" value="{{ $filters['package'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-currency-filter" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.currency') }}</label>
            <select id="booking-currency-filter" name="currency" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="">{{ __('messages.admin_bookings.all_currencies') }}</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency }}" @selected(($filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-created-from" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.created_from') }}</label>
            <input id="booking-created-from" name="created_from" type="date" value="{{ $filters['created_from'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-created-to" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.created_to') }}</label>
            <input id="booking-created-to" name="created_to" type="date" value="{{ $filters['created_to'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-departure-from" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.departure_from') }}</label>
            <input id="booking-departure-from" name="departure_from" type="date" value="{{ $filters['departure_from'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-departure-to" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.departure_to') }}</label>
            <input id="booking-departure-to" name="departure_to" type="date" value="{{ $filters['departure_to'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-sort" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.sort') }}</label>
            <select id="booking-sort" name="sort" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                @foreach (['newest', 'departure', 'amount', 'status'] as $sort)
                    <option value="{{ $sort }}" @selected(($filters['sort'] ?? 'newest') === $sort)>{{ __('messages.admin_bookings.sort_options.'.$sort) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="booking-direction" class="text-xs font-semibold admin-heading">{{ __('messages.admin_bookings.direction') }}</label>
            <select id="booking-direction" name="direction" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>{{ __('messages.admin_bookings.newest_first') }}</option>
                <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>{{ __('messages.admin_bookings.oldest_first') }}</option>
            </select>
        </div>
        @if ($errors->any())
            <div role="alert" class="grid gap-1 text-sm font-semibold text-rose-800 dark:text-rose-200 sm:col-span-2 xl:col-span-4">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <div class="flex min-w-0 flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.admin_bookings.apply_filters') }}
            </button>
            <a href="{{ route($currentRoute, ['lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_bookings.clear_filters') }}</a>
            <a href="{{ $exportUrl }}" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="download" class="h-4 w-4" />
                {{ __('messages.admin_bookings.export') }}
            </a>
        </div>
    </form>

    <form method="GET" action="{{ $exportUrl }}" class="grid min-w-0 gap-3">
        <div class="admin-empty-state overflow-x-auto border">
            <table class="w-full min-w-[1050px] border-collapse text-left text-sm">
                <thead class="admin-surface-soft text-xs uppercase tracking-wide admin-muted">
                    <tr>
                        <th class="p-3"><span class="sr-only">{{ __('messages.admin_bookings.select_for_export') }}</span></th>
                        <th class="p-3">{{ __('messages.admin_bookings.reference') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.traveler') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.agency') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.package_departure') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.travelers') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.price_snapshot') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.status') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.payment_status') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.created') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.disputes') }}</th>
                        <th class="p-3">{{ __('messages.admin_bookings.details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bookings as $booking)
                        @php
                            $bookingStatus = $booking->status->value;
                        @endphp
                        <tr class="border-t admin-border">
                            <td class="p-3"><input type="checkbox" name="booking_ids[]" value="{{ $booking->id }}" aria-label="{{ __('messages.admin_bookings.select_booking', ['reference' => $booking->booking_reference]) }}"></td>
                            <td class="p-3 font-semibold admin-heading">{{ $booking->booking_reference }}</td>
                            <td class="p-3">
                                <span class="block font-semibold admin-heading">{{ $booking->traveler?->name ?? __('messages.admin_bookings.unavailable') }}</span>
                                <span class="block text-xs admin-muted">{{ $booking->traveler?->email ?? __('messages.admin_bookings.unavailable') }}</span>
                            </td>
                            <td class="p-3 admin-heading">{{ $booking->agency?->company_name ?? __('messages.admin_bookings.unavailable') }}</td>
                            <td class="p-3">
                                <span class="block admin-heading">{{ $booking->package?->title ?? $booking->customRequest?->title ?? __('messages.admin_bookings.unavailable') }}</span>
                                <span class="block text-xs admin-muted">{{ $booking->customRequest?->reference ?? $booking->availability?->departure_date?->locale(app()->getLocale())->isoFormat('LL') ?? __('messages.admin_bookings.unavailable') }}</span>
                            </td>
                            <td class="p-3 admin-heading">{{ $booking->traveler_count }}</td>
                            <td class="p-3 whitespace-nowrap admin-heading">{{ $booking->total_amount }} {{ $booking->currency }}</td>
                            <td class="p-3">
                                <span class="inline-flex border px-2 py-1 text-xs font-semibold {{ $statusClasses[$bookingStatus] ?? '' }}">{{ __('messages.admin_bookings.statuses.'.$bookingStatus) }}</span>
                                @if ($bookingStatus === 'pending' && $booking->expires_at?->isPast())
                                    <span class="mt-1 block max-w-36 text-xs font-semibold text-amber-800 dark:text-amber-200">{{ __('messages.admin_bookings.expiry_discrepancy') }}</span>
                                @endif
                            </td>
                            <td class="p-3 admin-heading">{{ __('messages.admin_bookings.payment_statuses.'.$booking->payment_status->value) }}</td>
                            <td class="p-3 whitespace-nowrap admin-muted">{{ $booking->created_at?->locale(app()->getLocale())->isoFormat('LL') }}</td>
                            <td class="p-3">
                                @if (($booking->booking_disputes_count ?? 0) > 0)
                                    <a class="font-semibold underline" href="{{ route(config('admin_dashboard.page_routes.booking_disputes'), ['lang' => app()->getLocale(), 'search' => $booking->booking_reference]) }}">{{ $booking->booking_disputes_count }}</a>
                                @else
                                    <span class="admin-muted">0</span>
                                @endif
                            </td>
                            <td class="p-3">
                                <a class="font-semibold underline" href="{{ route('admin.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}">{{ __('messages.admin_bookings.open') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="p-8 text-center admin-muted">{{ __('messages.admin_bookings.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="download" class="h-4 w-4" />
                {{ __('messages.admin_bookings.export_selected') }}
            </button>
            <div>{{ $bookings->links() }}</div>
        </div>
    </form>
</section>
