<section class="grid gap-6" aria-label="{{ __('messages.admin_nav.pages.reported_content') }}" data-admin-reported-content>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <div class="flex flex-col gap-2 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
        <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.trust_safety.reports_intro') }}</p>
        <p class="shrink-0 text-xs admin-muted">{{ __('messages.trust_safety.results_count', ['count' => $reports->total()]) }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'reported_content', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2">
        <div class="grid min-w-0 gap-1.5">
            <label for="reports-search" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.search') }}</label>
            <input id="reports-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.trust_safety.search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="reports-status" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.status') }}</label>
            <select id="reports-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all">{{ __('messages.trust_safety.all_statuses') }}</option>
                @foreach (['open', 'investigating', 'dismissed', 'restricted', 'removed'] as $status)
                    <option value="{{ $status }}" @selected(request('status', 'all') === $status)>{{ __('messages.trust_safety.status_labels.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-2 sm:col-span-2">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.trust_safety.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => 'reported_content', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">
                {{ __('messages.trust_safety.clear_filters') }}
            </a>
        </div>
    </form>

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="admin-empty-state overflow-x-auto border">
        <table class="w-full min-w-[68rem] text-left text-sm">
            <thead class="border-b admin-border">
                <tr class="text-xs uppercase tracking-wide admin-muted">
                    <th scope="col" class="px-4 py-3">#</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.reason') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.reporter') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.assignee') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.status') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.created') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.admin_users.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y admin-border">
                @forelse ($reports as $report)
                    @php
                        $isClosed = in_array($report->status, ['dismissed', 'restricted', 'removed'], true);
                        $reportedReview = $report->review;
                    @endphp
                    <tr data-admin-report-id="{{ $report->id }}">
                        <td class="whitespace-nowrap px-4 py-3 font-semibold admin-heading">#{{ $report->id }}</td>
                        <td class="max-w-56 truncate px-4 py-3 admin-heading" title="{{ $report->reason }}">{{ $report->reason }}</td>
                        <td class="max-w-40 truncate px-4 py-3 admin-heading">{{ $report->reporter?->name ?? '—' }}</td>
                        <td class="max-w-40 truncate px-4 py-3 admin-heading">{{ $report->assignee?->name ?? __('messages.trust_safety.unassigned') }}</td>
                        <td class="px-4 py-3"><span class="admin-badge inline-flex border px-2 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$report->status) }}</span></td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs admin-muted"><time datetime="{{ $report->created_at->toIso8601String() }}">{{ $report->created_at->format('M j, Y · H:i') }}</time></td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.reports.show', array_filter(['report' => $report->id, 'lang' => app()->getLocale(), 'search' => request('search'), 'status' => request('status')])) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">
                                {{ __('messages.admin_users.view_details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center admin-muted">{{ __('messages.trust_safety.no_reports') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $reports->links() }}
</section>
