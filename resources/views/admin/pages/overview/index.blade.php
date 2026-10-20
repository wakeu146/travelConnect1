<section class="grid gap-6" aria-label="{{ __('messages.admin_overview.title') }}" data-admin-overview>
    <div class="flex flex-col gap-4 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
        <div class="grid gap-1.5">
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_overview.introduction') }}</p>
            <p class="text-xs admin-muted">
                {{ __('messages.admin_overview.updated_at', ['time' => now()->locale(app()->getLocale())->isoFormat('LLL')]) }}
            </p>
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('messages.admin_overview.quick_actions') }}">
            <a
                href="{{ route('admin.dashboard.page', ['page' => 'pending_verification', 'lang' => app()->getLocale()]) }}"
                class="admin-nav-link inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold"
            >
                <x-admin.icon name="shield_check" class="h-4 w-4" />
                {{ __('messages.admin_overview.review_agencies') }}
            </a>
            <a
                href="{{ route('admin.dashboard.page', ['page' => 'all_bookings', 'lang' => app()->getLocale()]) }}"
                class="admin-nav-link inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold"
            >
                <x-admin.icon name="calendar" class="h-4 w-4" />
                {{ __('messages.admin_overview.view_bookings') }}
            </a>
        </nav>
    </div>

    @include('admin.pages.overview.partials.snapshot-cards')

    @include('admin.pages.overview.partials.status-breakdowns')

    <div class="grid gap-6 xl:grid-cols-2">
        @include('admin.pages.overview.partials.attention-queue')
        @include('admin.pages.overview.partials.recent-activity')
    </div>
</section>
