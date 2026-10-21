<section class="admin-empty-state admin-overview-card grid content-start gap-5 border p-5 sm:p-6" aria-labelledby="overview-activity-title">
    <header class="grid gap-1">
        <h2 id="overview-activity-title" class="text-base font-bold admin-heading">{{ __('messages.admin_overview.activity_title') }}</h2>
        <p class="text-sm leading-5 admin-muted">{{ __('messages.admin_overview.activity_description') }}</p>
    </header>

    @if (count($overview['recent_activity']) > 0)
        <ol class="grid gap-1">
            @foreach ($overview['recent_activity'] as $activity)
                @php
                    [$activityLabel, $activityPage, $activityIcon] = match ($activity['type']) {
                        'traveler' => [__('messages.admin_overview.activity_traveler'), 'travelers', 'users'],
                        'agency' => [__('messages.admin_overview.activity_agency'), 'all_agencies', 'building'],
                        'package' => [__('messages.admin_overview.activity_package'), 'all_travel_packages', 'map'],
                        'booking' => [__('messages.admin_overview.activity_booking'), 'all_bookings', 'calendar'],
                        'inquiry' => [__('messages.admin_overview.activity_inquiry'), 'traveler_inquiries', 'message'],
                        'travel_request' => [__('messages.admin_overview.activity_travel_request'), 'new_requests', 'clipboard'],
                        'traveler_issue' => [__('messages.admin_overview.activity_traveler_issue'), 'alerts', 'message'],
                    };
                @endphp
                <li>
                    <a
                        href="{{ route('admin.dashboard.page', ['page' => $activityPage, 'lang' => app()->getLocale()]) }}"
                        class="group flex min-h-16 items-start gap-3 rounded-lg px-2 py-3 transition hover:bg-[var(--admin-surface-soft)]"
                        data-activity-type="{{ $activity['type'] }}"
                    >
                        <span class="admin-empty-icon mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg">
                            <x-admin.icon :name="$activityIcon" class="h-4 w-4" />
                        </span>
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="text-xs font-semibold admin-accent">{{ $activityLabel }}</span>
                            <span class="break-words text-sm font-semibold admin-heading">{{ $activity['title'] }}</span>
                            <time class="text-xs admin-muted" datetime="{{ $activity['created_at']->toIso8601String() }}">
                                {{ $activity['created_at']->locale(app()->getLocale())->diffForHumans() }}
                            </time>
                        </span>
                        <x-admin.icon name="chevron_right" class="mt-1 h-4 w-4 shrink-0 admin-muted transition group-hover:translate-x-0.5" />
                    </a>
                </li>
            @endforeach
        </ol>
    @else
        <div class="grid justify-items-center gap-3 py-5 text-center" data-activity-empty>
            <span class="admin-empty-icon flex h-10 w-10 items-center justify-center rounded-xl">
                <x-admin.icon name="inbox" class="h-5 w-5" />
            </span>
            <p class="text-sm font-semibold admin-heading">{{ __('messages.admin_overview.no_recent_activity') }}</p>
        </div>
    @endif
</section>
