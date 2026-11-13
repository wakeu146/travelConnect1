<section class="grid min-w-0 gap-6" data-admin-analytics>
    <p class="max-w-4xl text-sm leading-6 admin-muted">{{ __('messages.admin_analytics.intro') }}</p>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => $activePage, 'lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 border p-4 admin-border sm:flex-row sm:items-end">
        <label class="grid flex-1 gap-1 text-xs font-semibold admin-muted">
            {{ __('messages.admin_analytics.date_from') }}
            <input type="date" name="created_from" value="{{ $analyticsData['from'] }}" class="admin-input min-h-10 w-full min-w-0 px-3 text-sm">
        </label>
        <label class="grid flex-1 gap-1 text-xs font-semibold admin-muted">
            {{ __('messages.admin_analytics.date_to') }}
            <input type="date" name="created_to" value="{{ $analyticsData['to'] }}" class="admin-input min-h-10 w-full min-w-0 px-3 text-sm">
        </label>
        <button type="submit" class="admin-nav-link min-h-10 border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_analytics.apply_date_filter') }}</button>
        <div class="flex flex-wrap gap-2">
            @foreach (['csv', 'json'] as $format)
                <a href="{{ route('admin.analytics.export', ['lang' => app()->getLocale(), 'created_from' => $analyticsData['from'], 'created_to' => $analyticsData['to'], 'format' => $format]) }}" class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_analytics.export_'.$format) }}</a>
            @endforeach
        </div>
    </form>

    @if ($activePage === 'platform_performance')
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['key' => 'new_users', 'label' => __('messages.admin_analytics.metrics.new_users')],
                ['key' => 'new_agencies', 'label' => __('messages.admin_analytics.metrics.new_agencies')],
                ['key' => 'bookings', 'label' => __('messages.admin_analytics.metrics.bookings')],
                ['key' => 'travel_requests', 'label' => __('messages.admin_analytics.metrics.travel_requests')],
            ] as $metric)
                <article class="admin-empty-state grid gap-2 border p-4" data-analytics-metric="{{ $metric['key'] }}" data-analytics-value="{{ $analyticsData['metrics'][$metric['key']] }}">
                    <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ $metric['label'] }}</p>
                    <p class="text-2xl font-bold tracking-tight admin-heading">{{ number_format($analyticsData['metrics'][$metric['key']]) }}</p>
                </article>
            @endforeach
        </div>

        <div class="grid min-w-0 gap-5 xl:grid-cols-2">
            @include('admin.pages.business-insights.analytics.partials.trend-chart', [
                'chart' => $analyticsData['growthTrend'],
                'chartKey' => 'analytics-growth',
                'title' => __('messages.admin_analytics.growth_title'),
                'description' => __('messages.admin_analytics.growth_description'),
            ])
            @include('admin.pages.business-insights.analytics.partials.trend-chart', [
                'chart' => $analyticsData['activityTrend'],
                'chartKey' => 'analytics-activity',
                'title' => __('messages.admin_analytics.activity_title'),
                'description' => __('messages.admin_analytics.activity_description'),
            ])
        </div>

        <div class="grid min-w-0 gap-5 xl:grid-cols-2">
            @include('admin.pages.business-insights.analytics.partials.bar-chart', [
                'chartKey' => 'analytics-booking-status',
                'title' => __('messages.admin_analytics.booking_status_title'),
                'description' => __('messages.admin_analytics.booking_status_description'),
                'items' => $analyticsData['bookingStatuses'],
                'labelType' => 'booking_statuses',
                'labelKey' => 'status',
            ])
            @include('admin.pages.business-insights.analytics.partials.bar-chart', [
                'chartKey' => 'analytics-destinations',
                'title' => __('messages.admin_analytics.destinations_title'),
                'description' => __('messages.admin_analytics.destinations_description'),
                'items' => $analyticsData['destinations'],
                'labelType' => null,
                'labelKey' => 'name',
            ])
        </div>
    @elseif ($activePage === 'user_growth')
        @include('admin.pages.business-insights.analytics.partials.trend-chart', [
            'chart' => $analyticsData['userGrowthTrend'],
            'chartKey' => 'analytics-user-growth',
            'title' => __('messages.admin_analytics.user_growth_title'),
            'description' => __('messages.admin_analytics.user_growth_description'),
        ])
        @include('admin.pages.business-insights.analytics.partials.bar-chart', [
            'chartKey' => 'analytics-user-roles',
            'title' => __('messages.admin_analytics.user_roles_title'),
            'description' => __('messages.admin_analytics.user_roles_description'),
            'items' => $analyticsData['userRoles'],
            'labelType' => 'user_roles',
            'labelKey' => 'status',
        ])
    @elseif ($activePage === 'agency_growth')
        @include('admin.pages.business-insights.analytics.partials.trend-chart', [
            'chart' => $analyticsData['agencyGrowthTrend'],
            'chartKey' => 'analytics-agency-growth',
            'title' => __('messages.admin_analytics.agency_growth_title'),
            'description' => __('messages.admin_analytics.agency_growth_description'),
        ])
        <div class="grid min-w-0 gap-5 xl:grid-cols-2">
            @include('admin.pages.business-insights.analytics.partials.bar-chart', [
                'chartKey' => 'analytics-verification-status',
                'title' => __('messages.admin_analytics.verification_status_title'),
                'description' => __('messages.admin_analytics.verification_status_description'),
                'items' => $analyticsData['verificationStatuses'],
                'labelType' => 'verification_statuses',
                'labelKey' => 'status',
            ])
            @include('admin.pages.business-insights.analytics.partials.trend-chart', [
                'chart' => $analyticsData['verificationTrend'],
                'chartKey' => 'analytics-verification-trend',
                'title' => __('messages.admin_analytics.verification_trend_title'),
                'description' => __('messages.admin_analytics.verification_trend_description'),
            ])
        </div>
    @elseif ($activePage === 'booking_analytics')
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ([
                ['key' => 'bookings', 'label' => __('messages.admin_analytics.metrics.bookings')],
                ['key' => 'cancellations', 'label' => __('messages.admin_analytics.metrics.cancellations')],
            ] as $metric)
                <article class="admin-empty-state grid gap-2 border p-4" data-analytics-metric="{{ $metric['key'] }}" data-analytics-value="{{ $analyticsData['metrics'][$metric['key']] }}">
                    <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ $metric['label'] }}</p>
                    <p class="text-2xl font-bold tracking-tight admin-heading">{{ number_format($analyticsData['metrics'][$metric['key']]) }}</p>
                </article>
            @endforeach
        </div>
        <p class="border border-dashed p-4 text-sm leading-6 admin-muted">{{ __('messages.admin_analytics.conversion_unavailable') }}</p>
        @include('admin.pages.business-insights.analytics.partials.trend-chart', [
            'chart' => $analyticsData['bookingTrend'],
            'chartKey' => 'analytics-booking-trend',
            'title' => __('messages.admin_analytics.booking_trend_title'),
            'description' => __('messages.admin_analytics.booking_trend_description'),
        ])
        <div class="grid min-w-0 gap-5 xl:grid-cols-2">
            @include('admin.pages.business-insights.analytics.partials.bar-chart', [
                'chartKey' => 'analytics-booking-status',
                'title' => __('messages.admin_analytics.booking_status_title'),
                'description' => __('messages.admin_analytics.booking_status_description'),
                'items' => $analyticsData['bookingStatuses'],
                'labelType' => 'booking_statuses',
                'labelKey' => 'status',
            ])
            @include('admin.pages.business-insights.analytics.partials.bar-chart', [
                'chartKey' => 'analytics-destinations',
                'title' => __('messages.admin_analytics.destinations_title'),
                'description' => __('messages.admin_analytics.destinations_description'),
                'items' => $analyticsData['destinations'],
                'labelType' => null,
                'labelKey' => 'name',
            ])
        </div>
    @elseif ($activePage === 'popular_destinations')
        @include('admin.pages.business-insights.analytics.partials.bar-chart', [
            'chartKey' => 'analytics-destinations',
            'title' => __('messages.admin_analytics.destinations_title'),
            'description' => __('messages.admin_analytics.destinations_description'),
            'items' => $analyticsData['destinations'],
            'labelType' => null,
            'labelKey' => 'name',
        ])
    @elseif ($activePage === 'package_performance')
        @include('admin.pages.business-insights.analytics.partials.bar-chart', [
            'chartKey' => 'analytics-package-performance',
            'title' => __('messages.admin_analytics.package_performance_title'),
            'description' => __('messages.admin_analytics.package_performance_description'),
            'items' => $analyticsData['packages'],
            'labelType' => null,
            'labelKey' => 'name',
        ])
    @elseif ($activePage === 'trust_verification_statistics')
        <div class="grid min-w-0 gap-5 xl:grid-cols-2">
            @include('admin.pages.business-insights.analytics.partials.bar-chart', [
                'chartKey' => 'analytics-verification-status',
                'title' => __('messages.admin_analytics.verification_status_title'),
                'description' => __('messages.admin_analytics.verification_status_description'),
                'items' => $analyticsData['verificationStatuses'],
                'labelType' => 'verification_statuses',
                'labelKey' => 'status',
            ])
            @include('admin.pages.business-insights.analytics.partials.trend-chart', [
                'chart' => $analyticsData['verificationTrend'],
                'chartKey' => 'analytics-verification-trend',
                'title' => __('messages.admin_analytics.verification_trend_title'),
                'description' => __('messages.admin_analytics.verification_trend_description'),
            ])
        </div>
        @include('admin.pages.business-insights.analytics.partials.trend-chart', [
            'chart' => $analyticsData['trustScoreTrend'],
            'chartKey' => 'analytics-trust-score',
            'title' => __('messages.admin_analytics.trust_score_title'),
            'description' => __('messages.admin_analytics.trust_score_description'),
        ])
    @elseif ($activePage === 'export_reports')
        <section class="admin-empty-state grid gap-2 border p-5" aria-labelledby="analytics-export-title">
            <h2 id="analytics-export-title" class="text-base font-bold admin-heading">{{ __('messages.admin_analytics.export_title') }}</h2>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_analytics.export_description') }}</p>
        </section>
    @endif
</section>
