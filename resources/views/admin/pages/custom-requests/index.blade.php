<section class="grid gap-6" data-admin-custom-requests data-custom-request-page>
    @if (session('status'))<p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>@endif
    <nav class="flex flex-wrap gap-2 border-b pb-4 admin-border" aria-label="{{ __('messages.custom_requests.queue_navigation') }}" data-custom-request-queues>
        @foreach ([
            'all_requests' => $summary['total'],
            'new_requests' => $summary['new'],
            'in_progress_requests' => $summary['in_progress'],
            'quoted_requests' => $summary['quoted'],
            'accepted_converted_requests' => $summary['converted'],
            'closed_requests' => $summary['closed'],
        ] as $page => $count)
            <a href="{{ route('admin.dashboard.page', ['page' => $page, 'lang' => app()->getLocale()]) }}" @if ($queue === $page) aria-current="page" @endif class="admin-nav-link inline-flex min-h-9 items-center gap-2 border px-3 py-1.5 text-xs font-semibold {{ $queue === $page ? 'is-active' : '' }}">
                {{ __('messages.admin_nav.pages.'.$page) }} <span class="admin-muted">{{ $count }}</span>
            </a>
        @endforeach
    </nav>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ([
            'total' => __('messages.custom_requests.total'),
            'new' => __('messages.admin_nav.pages.new_requests'),
            'in_progress' => __('messages.admin_nav.pages.in_progress_requests'),
            'quoted' => __('messages.admin_nav.pages.quoted_requests'),
            'converted' => __('messages.admin_nav.pages.accepted_converted_requests'),
            'closed' => __('messages.admin_nav.pages.closed_requests'),
        ] as $key => $label)
            <article class="admin-empty-state grid gap-2 border p-4">
                <p class="text-xs font-semibold admin-muted">{{ $label }}</p>
                <p class="text-2xl font-bold admin-heading">{{ $summary[$key] }}</p>
            </article>
        @endforeach
    </div>
    <form method="GET" action="{{ route('admin.custom-requests.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 border p-4 admin-border sm:grid-cols-3">
        <input type="hidden" name="queue" value="{{ $queue }}">
        <label class="grid gap-1 text-xs font-semibold admin-muted">{{ __('messages.custom_requests.search') }}<input name="search" value="{{ $filters['search'] ?? '' }}" class="admin-input h-10 border px-3 text-sm"></label>
        <label class="grid gap-1 text-xs font-semibold admin-muted">{{ __('messages.custom_requests.status') }}<select name="status" class="admin-input h-10 border px-3 text-sm"><option value="">{{ __('messages.custom_requests.all') }}</option>@foreach (['draft','open','on_hold','converted','closed','withdrawn','rejected','expired'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('messages.custom_requests.statuses.'.$status) }}</option>@endforeach</select></label>
        <label class="grid gap-1 text-xs font-semibold admin-muted">{{ __('messages.custom_requests.sharing') }}<select name="sharing_mode" class="admin-input h-10 border px-3 text-sm"><option value="">{{ __('messages.custom_requests.all') }}</option><option value="all" @selected(($filters['sharing_mode'] ?? '') === 'all')>{{ __('messages.custom_requests.share_all') }}</option><option value="selected" @selected(($filters['sharing_mode'] ?? '') === 'selected')>{{ __('messages.custom_requests.select_agencies') }}</option></select></label>
        <div class="flex flex-wrap gap-2 sm:col-span-3"><button class="admin-nav-link border px-4 py-2 text-sm font-semibold">{{ __('messages.custom_requests.apply_filters') }}</button><a href="{{ route('admin.custom-requests.export', request()->query()) }}" class="admin-nav-link border px-4 py-2 text-sm font-semibold">{{ __('messages.custom_requests.export_csv') }}</a></div>
    </form>
    <div class="admin-empty-state overflow-x-auto border" data-admin-custom-requests-queue="{{ $queue }}">
        <table class="w-full min-w-[850px] text-left text-sm">
            <thead class="border-b admin-border"><tr class="text-xs admin-muted"><th class="p-3">{{ __('messages.custom_requests.reference') }}</th><th class="p-3">{{ __('messages.custom_requests.traveler') }}</th><th class="p-3">{{ __('messages.custom_requests.destination') }}</th><th class="p-3">{{ __('messages.custom_requests.sharing') }}</th><th class="p-3">{{ __('messages.custom_requests.agencies_contacted') }}</th><th class="p-3">{{ __('messages.custom_requests.proposal_count') }}</th><th class="p-3">{{ __('messages.custom_requests.status') }}</th><th class="p-3">{{ __('messages.custom_requests.booking') }}</th></tr></thead>
            <tbody>@forelse ($requests as $travelRequest)<tr class="border-b admin-border"><td class="p-3"><a class="font-bold underline" href="{{ route('admin.custom-requests.show', ['travelRequest' => $travelRequest->id, 'queue' => $queue, 'lang' => app()->getLocale()]) }}">{{ $travelRequest->reference }}</a><span class="mt-1 block text-xs admin-muted">{{ $travelRequest->title }}</span></td><td class="p-3">{{ $travelRequest->traveler?->name }}</td><td class="p-3">{{ $travelRequest->destination }}</td><td class="p-3">{{ $travelRequest->sharing_mode }}</td><td class="p-3">{{ $travelRequest->recipients_count }}</td><td class="p-3">{{ $travelRequest->proposals_count }}</td><td class="p-3">{{ __('messages.custom_requests.statuses.'.$travelRequest->status) }}</td><td class="p-3">{{ $travelRequest->booking?->booking_reference ?? __('messages.custom_requests.no_booking') }}</td></tr>@empty<tr><td colspan="8" class="p-8 text-center admin-muted">{{ __('messages.custom_requests.empty_title') }}</td></tr>@endforelse</tbody>
        </table>
    </div>
    {{ $requests->links() }}
</section>
