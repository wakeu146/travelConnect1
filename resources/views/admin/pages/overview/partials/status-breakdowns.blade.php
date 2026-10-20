@php
    $agencyStatuses = [
        [
            'key' => 'pending',
            'label' => __('messages.admin_overview.status_pending'),
            'page' => 'pending_verification',
        ],
        [
            'key' => 'verified',
            'label' => __('messages.admin_overview.status_verified'),
            'page' => 'verified_agencies',
        ],
        [
            'key' => 'rejected',
            'label' => __('messages.admin_overview.status_rejected'),
            'page' => 'rejected',
        ],
    ];

    $bookingStatuses = [
        [
            'key' => 'pending',
            'label' => __('messages.admin_overview.status_pending'),
            'page' => 'pending_bookings',
        ],
        [
            'key' => 'confirmed',
            'label' => __('messages.admin_overview.status_confirmed'),
            'page' => 'confirmed_bookings',
        ],
        [
            'key' => 'completed',
            'label' => __('messages.admin_overview.status_completed'),
            'page' => 'completed_bookings',
        ],
        [
            'key' => 'cancelled',
            'label' => __('messages.admin_overview.status_cancelled'),
            'page' => 'cancelled_rejected_bookings',
        ],
        [
            'key' => 'rejected',
            'label' => __('messages.admin_overview.status_rejected'),
            'page' => 'cancelled_rejected_bookings',
        ],
        [
            'key' => 'expired',
            'label' => __('messages.admin_overview.status_expired'),
            'page' => 'expired_bookings',
        ],
    ];
@endphp

<section class="grid gap-6 xl:grid-cols-2" aria-label="{{ __('messages.admin_overview.status_breakdowns') }}">
    <section class="admin-empty-state admin-overview-card grid content-start gap-5 border p-5 sm:p-6" aria-labelledby="overview-agency-status-title">
        <header class="flex items-start justify-between gap-3">
            <div class="grid gap-1">
                <h2 id="overview-agency-status-title" class="text-base font-bold admin-heading">{{ __('messages.admin_overview.agency_status_title') }}</h2>
                <p class="text-sm leading-5 admin-muted">{{ __('messages.admin_overview.agency_status_description') }}</p>
            </div>
            <span class="admin-badge inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold" data-agency-status-total>{{ \Illuminate\Support\Number::format($overview['agencies'], locale: app()->getLocale()) }}</span>
        </header>

        @if ($overview['agencies'] > 0)
            <ul class="grid divide-y admin-border">
                @foreach ($agencyStatuses as $status)
                    <li>
                        <a
                            href="{{ route('admin.dashboard.page', ['page' => $status['page'], 'lang' => app()->getLocale()]) }}"
                            class="admin-overview-status-row flex min-h-12 items-center justify-between gap-3 py-2.5"
                            data-agency-status="{{ $status['key'] }}"
                        >
                            <span class="text-sm font-semibold admin-heading">{{ $status['label'] }}</span>
                            <span class="text-sm font-bold admin-muted" data-agency-status-count="{{ $status['key'] }}">{{ \Illuminate\Support\Number::format($overview['agency_statuses'][$status['key']], locale: app()->getLocale()) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="admin-overview-empty-state border px-4 py-5 text-center text-sm admin-muted" data-agency-status-empty>
                {{ __('messages.admin_overview.no_agencies_yet') }}
            </p>
        @endif
    </section>

    <section class="admin-empty-state admin-overview-card grid content-start gap-5 border p-5 sm:p-6" aria-labelledby="overview-booking-status-title">
        <header class="flex items-start justify-between gap-3">
            <div class="grid gap-1">
                <h2 id="overview-booking-status-title" class="text-base font-bold admin-heading">{{ __('messages.admin_overview.booking_status_title') }}</h2>
                <p class="text-sm leading-5 admin-muted">{{ __('messages.admin_overview.booking_status_description') }}</p>
            </div>
            <span class="admin-badge inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold" data-booking-status-total>{{ \Illuminate\Support\Number::format($overview['bookings'], locale: app()->getLocale()) }}</span>
        </header>

        @if ($overview['bookings'] > 0)
            <ul class="grid divide-y admin-border">
                @foreach ($bookingStatuses as $status)
                    <li>
                        <a
                            href="{{ route('admin.dashboard.page', ['page' => $status['page'], 'lang' => app()->getLocale()]) }}"
                            class="admin-overview-status-row flex min-h-12 items-center justify-between gap-3 py-2.5"
                            data-booking-status="{{ $status['key'] }}"
                        >
                            <span class="text-sm font-semibold admin-heading">{{ $status['label'] }}</span>
                            <span class="text-sm font-bold admin-muted" data-booking-status-count="{{ $status['key'] }}">{{ \Illuminate\Support\Number::format($overview['booking_statuses'][$status['key']], locale: app()->getLocale()) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="admin-overview-empty-state border px-4 py-5 text-center text-sm admin-muted" data-booking-status-empty>
                {{ __('messages.admin_overview.no_bookings_yet') }}
            </p>
        @endif
    </section>
</section>
