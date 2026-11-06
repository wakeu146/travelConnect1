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

    @forelse ($reports as $report)
        @php
            $isClosed = in_array($report->status, ['dismissed', 'restricted', 'removed'], true);
            $reportedReview = $report->review;
        @endphp
        <article class="admin-empty-state grid gap-5 border p-4 sm:p-5" data-admin-report-id="{{ $report->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="grid gap-2">
                    <h2 class="text-base font-bold admin-heading">{{ __('messages.trust_safety.reported_item') }} #{{ $report->id }}</h2>
                    <span class="admin-badge w-fit rounded-full px-2.5 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$report->status) }}</span>
                </div>
                <p class="text-xs admin-muted">{{ __('messages.trust_safety.created') }} · <time datetime="{{ $report->created_at->toIso8601String() }}">{{ $report->created_at->format('M j, Y · H:i') }}</time></p>
            </div>

            <dl class="grid gap-3 rounded-lg border p-4 text-sm admin-border sm:grid-cols-2">
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.reporter') }}</dt>
                    <dd class="font-medium admin-heading">{{ $report->reporter?->name ?? '—' }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.assignee') }}</dt>
                    <dd class="font-medium admin-heading">{{ $report->assignee?->name ?? __('messages.trust_safety.unassigned') }}</dd>
                </div>
            </dl>

            @if ($reportedReview)
                <div class="grid gap-3 border-l-2 border-rose-500/50 pl-4">
                    <h3 class="text-sm font-bold admin-heading">{{ __('messages.trust_safety.reported_item') }}</h3>
                    <p class="text-sm leading-6 admin-heading">{{ $reportedReview->comment }}</p>
                    <p class="text-xs leading-5 admin-muted">
                        {{ $reportedReview->user?->name ?? '—' }} ·
                        {{ $reportedReview->agency?->company_name ?? '—' }} ·
                        {{ __('messages.trust_safety.rating', ['rating' => $reportedReview->rating]) }}
                        @if ($reportedReview->booking?->package)
                            · {{ $reportedReview->booking->package->title }}
                        @endif
                    </p>
                </div>
            @endif

            <dl class="grid gap-3 border-t pt-4 admin-border sm:grid-cols-2">
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.reason') }}</dt>
                    <dd class="text-sm leading-6 admin-heading">{{ $report->reason }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.evidence') }}</dt>
                    <dd class="text-sm leading-6 admin-heading">{{ $report->evidence ?: __('messages.trust_safety.no_evidence') }}</dd>
                </div>
            </dl>

            <div class="grid gap-1">
                <h3 class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.review_outcome') }}</h3>
                <p class="text-sm leading-6 admin-heading">{{ $report->outcome_reason ?: __('messages.trust_safety.no_outcome') }}</p>
            </div>

            <div class="grid gap-4 border-t pt-4 admin-border lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                <details class="grid gap-3">
                    <summary class="cursor-pointer text-sm font-semibold admin-heading">{{ __('messages.trust_safety.audit_history') }}</summary>
                    @if ($report->events->isNotEmpty())
                        <ol class="grid gap-3 border-l pl-4">
                            @foreach ($report->events as $event)
                                <li class="grid gap-1 text-sm">
                                    <span class="font-semibold admin-heading">{{ __('messages.trust_safety.action_labels.'.$event->action) }}</span>
                                    <span class="text-xs admin-muted">{{ $event->actor?->name ?? __('messages.trust_safety.system_actor') }} · {{ $event->created_at->format('M j, Y · H:i') }}</span>
                                    @if ($event->reason)
                                        <span class="text-sm leading-5 admin-muted">{{ $event->reason }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm admin-muted">{{ __('messages.trust_safety.no_history') }}</p>
                    @endif
                </details>

                @if (! $isClosed)
                    <form method="POST" action="{{ route('admin.reports.action', ['report' => $report->id, 'lang' => app()->getLocale()]) }}" class="grid content-start gap-3">
                        @csrf
                        <div class="grid gap-1.5">
                            <label for="report-action-{{ $report->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.action') }}</label>
                            <select id="report-action-{{ $report->id }}" name="action" required class="admin-input min-h-10 w-full border px-3 text-sm">
                                @foreach (['investigate', 'dismiss', 'restrict', 'remove'] as $action)
                                    <option value="{{ $action }}" @selected(old('action') === $action)>{{ __('messages.trust_safety.'.$action) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <label for="report-assignee-{{ $report->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.assignee') }}</label>
                            <select id="report-assignee-{{ $report->id }}" name="assignee_id" class="admin-input min-h-10 w-full border px-3 text-sm">
                                <option value="">{{ __('messages.trust_safety.select_admin') }}</option>
                                @foreach ($administrators as $administrator)
                                    <option value="{{ $administrator->id }}" @selected((string) old('assignee_id', $report->assigned_to) === (string) $administrator->id)>{{ $administrator->name }}</option>
                                @endforeach
                            </select>
                            @error('assignee_id')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                        </div>
                        <div class="grid gap-1.5">
                            <label for="report-reason-{{ $report->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.reason') }}</label>
                            <textarea id="report-reason-{{ $report->id }}" name="reason" required minlength="10" maxlength="2000" rows="2" class="admin-input w-full border px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                            @error('reason')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                        </div>
                        <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.trust_safety.save_action') }}</button>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.trust_safety.no_reports') }}</p>
    @endforelse

    {{ $reports->links() }}
</section>
