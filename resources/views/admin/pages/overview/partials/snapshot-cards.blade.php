<section class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-5" aria-label="{{ __('messages.admin_overview.snapshot') }}">
    @php
        $snapshotCards = [
            [
                'key' => 'travelers',
                'label' => __('messages.admin_overview.travelers'),
                'value' => $overview['travelers'],
                'icon' => 'users',
                'page' => 'travelers',
            ],
            [
                'key' => 'online-users',
                'label' => __('messages.admin_overview.online_users'),
                'value' => $overview['online_users'],
                'icon' => 'users',
                'page' => 'all_users',
            ],
            [
                'key' => 'agencies',
                'label' => __('messages.admin_overview.agencies'),
                'value' => $overview['agencies'],
                'icon' => 'building',
                'page' => 'all_agencies',
            ],
            [
                'key' => 'published-packages',
                'label' => __('messages.admin_overview.published_packages'),
                'value' => $overview['published_packages'],
                'icon' => 'map',
                'page' => 'published_packages',
            ],
            [
                'key' => 'bookings',
                'label' => __('messages.admin_overview.bookings'),
                'value' => $overview['bookings'],
                'icon' => 'calendar',
                'page' => 'all_bookings',
            ],
        ];
    @endphp

    @foreach ($snapshotCards as $card)
        <a
            href="{{ route('admin.dashboard.page', ['page' => $card['page'], 'lang' => app()->getLocale()]) }}"
            class="admin-empty-state admin-overview-card group grid content-start gap-5 border p-5 transition hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2"
            data-overview-metric="{{ $card['key'] }}"
        >
            <span class="admin-empty-icon flex h-11 w-11 items-center justify-center rounded-xl transition group-hover:scale-105">
                <x-admin.icon :name="$card['icon']" class="h-5 w-5" />
            </span>
            <span class="grid gap-1">
                <span class="text-3xl font-extrabold tracking-tight admin-heading" data-overview-metric-value="{{ $card['key'] }}">{{ \Illuminate\Support\Number::format($card['value'], locale: app()->getLocale()) }}</span>
                <span class="text-sm font-semibold admin-muted">{{ $card['label'] }}</span>
            </span>
        </a>
    @endforeach
</section>
