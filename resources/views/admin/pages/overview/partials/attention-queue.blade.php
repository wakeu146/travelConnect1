@php
    $attentionItems = [
        [
            'key' => 'flagged-reviews',
            'count' => $overview['flagged_reviews'],
            'label' => __('messages.admin_overview.flagged_reviews'),
            'description' => __('messages.admin_overview.flagged_reviews_description'),
            'icon' => 'flag',
            'page' => 'reported_content',
        ],
        [
            'key' => 'pending-verifications',
            'count' => $overview['pending_verifications'],
            'label' => __('messages.admin_overview.pending_verifications'),
            'description' => __('messages.admin_overview.pending_verifications_description'),
            'icon' => 'shield_check',
            'page' => 'pending_verification',
        ],
        [
            'key' => 'pending-bookings',
            'count' => $overview['pending_bookings'],
            'label' => __('messages.admin_overview.pending_bookings'),
            'description' => __('messages.admin_overview.pending_bookings_description'),
            'icon' => 'calendar',
            'page' => 'pending_bookings',
        ],
        [
            'key' => 'open-inquiries',
            'count' => $overview['open_inquiries'],
            'label' => __('messages.admin_overview.open_inquiries'),
            'description' => __('messages.admin_overview.open_inquiries_description'),
            'icon' => 'message',
            'page' => 'traveler_inquiries',
        ],
        [
            'key' => 'open-travel-requests',
            'count' => $overview['open_travel_requests'],
            'label' => __('messages.admin_overview.open_travel_requests'),
            'description' => __('messages.admin_overview.open_travel_requests_description'),
            'icon' => 'clipboard',
            'page' => 'new_requests',
        ],
        [
            'key' => 'pending-reviews',
            'count' => $overview['pending_reviews'],
            'label' => __('messages.admin_overview.pending_reviews'),
            'description' => __('messages.admin_overview.pending_reviews_description'),
            'icon' => 'star',
            'page' => 'reviews_ratings',
        ],
    ];

    $itemsNeedingAttention = collect($attentionItems)->filter(fn (array $item): bool => $item['count'] > 0);
@endphp

<section class="admin-empty-state admin-overview-card grid content-start gap-5 border p-5 sm:p-6" aria-labelledby="overview-attention-title">
    <header class="grid gap-1">
        <h2 id="overview-attention-title" class="text-base font-bold admin-heading">{{ __('messages.admin_overview.attention_title') }}</h2>
        <p class="text-sm leading-5 admin-muted">{{ __('messages.admin_overview.attention_description') }}</p>
    </header>

    @if ($itemsNeedingAttention->isNotEmpty())
        <ul class="grid divide-y admin-border">
            @foreach ($itemsNeedingAttention as $item)
                <li>
                    <a
                        href="{{ route('admin.dashboard.page', ['page' => $item['page'], 'lang' => app()->getLocale()]) }}"
                        class="group flex min-h-16 items-center gap-3 py-3 first:pt-0 last:pb-0"
                        data-attention-item="{{ $item['key'] }}"
                    >
                        <span class="admin-empty-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-lg">
                            <x-admin.icon :name="$item['icon']" class="h-4.5 w-4.5" />
                        </span>
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="text-sm font-semibold admin-heading">{{ $item['label'] }}</span>
                            <span class="text-xs leading-5 admin-muted">{{ $item['description'] }}</span>
                        </span>
                        <span class="inline-flex min-w-8 items-center justify-center rounded-full px-2 py-1 text-xs font-bold admin-badge" data-attention-count="{{ $item['key'] }}">{{ \Illuminate\Support\Number::format($item['count'], locale: app()->getLocale()) }}</span>
                        <x-admin.icon name="chevron_right" class="h-4 w-4 shrink-0 admin-muted transition group-hover:translate-x-0.5" />
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <div class="grid justify-items-center gap-3 py-5 text-center" data-attention-empty>
            <span class="admin-empty-icon flex h-10 w-10 items-center justify-center rounded-xl">
                <x-admin.icon name="shield_check" class="h-5 w-5" />
            </span>
            <p class="text-sm font-semibold admin-heading">{{ __('messages.admin_overview.no_attention_items') }}</p>
        </div>
    @endif
</section>
