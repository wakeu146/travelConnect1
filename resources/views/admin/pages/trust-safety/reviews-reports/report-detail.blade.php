<section class="grid min-w-0 gap-6" data-admin-report-detail>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <a href="{{ route('admin.dashboard.page', ['page' => 'reported_content', 'lang' => app()->getLocale(), ...$listFilters]) }}" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-3 py-2 text-sm font-semibold">
        <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
        {{ __('messages.trust_safety.back_to_reports') }}
    </a>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="grid gap-1 border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <article class="admin-empty-state grid min-w-0 gap-6 border p-5 sm:p-7">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b pb-5 admin-border">
            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-wide admin-muted">{{ __('messages.trust_safety.report_detail_title', ['id' => $report->id]) }}</p>
                <h2 class="text-2xl font-bold admin-heading">{{ __('messages.trust_safety.reported_item') }}</h2>
                <time class="text-xs admin-muted" datetime="{{ $report->created_at->toIso8601String() }}">{{ $report->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
            </div>
            <div class="grid justify-items-start gap-2 sm:justify-items-end">
                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$report->status) }}</span>
                <span class="text-sm admin-muted">{{ __('messages.trust_safety.assignee') }}: {{ $report->assignee?->name ?? __('messages.trust_safety.unassigned') }}</span>
            </div>
        </header>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-1">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.reporter') }}</dt>
                <dd class="text-sm admin-heading">{{ $report->reporter?->name ?? '—' }}</dd>
                @if ($report->reporter?->email)
                    <dd class="text-xs admin-muted">{{ $report->reporter->email }}</dd>
                @endif
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.review_outcome') }}</dt>
                <dd class="text-sm admin-heading">{{ $report->outcome_reason ?: __('messages.trust_safety.no_outcome') }}</dd>
            </div>
        </dl>

        <section class="grid gap-3 border-t pt-5 admin-border">
            <h3 class="text-base font-semibold admin-heading">{{ __('messages.trust_safety.reason') }}</h3>
            <p class="whitespace-pre-line text-sm leading-6 admin-heading">{{ $report->reason }}</p>
            <h3 class="pt-2 text-xs font-semibold admin-muted">{{ __('messages.trust_safety.evidence') }}</h3>
            <p class="whitespace-pre-line text-sm leading-6 admin-heading">{{ $report->evidence ?: __('messages.trust_safety.no_evidence') }}</p>
        </section>

        @if ($report->review)
            <section class="grid gap-4 border-t pt-5 admin-border">
                <h3 class="text-base font-semibold admin-heading">{{ __('messages.trust_safety.reported_item') }}</h3>
                <div class="flex flex-wrap items-center gap-2 text-xs admin-muted">
                    <span>{{ __('messages.trust_safety.author') }}: {{ $report->review->user?->name ?? '—' }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ __('messages.trust_safety.related_agency') }}: {{ $report->review->agency?->company_name ?? '—' }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ __('messages.trust_safety.rating', ['rating' => $report->review->rating]) }}</span>
                </div>
                <blockquote class="whitespace-pre-line border-l-2 border-slate-400 pl-4 text-sm leading-7 admin-heading">{{ $report->review->comment }}</blockquote>
                <p class="text-xs admin-muted">{{ __('messages.trust_safety.related_package') }}: {{ $report->review->booking?->package?->title ?? '—' }}</p>
                <a href="{{ route('admin.reviews.show', ['review' => $report->review->id, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 w-fit items-center border px-3 py-1.5 text-xs font-semibold">
                    {{ __('messages.trust_safety.open_review') }}
                </a>
            </section>
        @endif

        <section class="grid gap-3 border-t pt-5 admin-border" aria-label="{{ __('messages.trust_safety.audit_history') }}">
            <h3 class="text-base font-semibold admin-heading">{{ __('messages.trust_safety.audit_history') }}</h3>
            @forelse ($report->events as $event)
                <div class="grid gap-1 border-b pb-3 last:border-0 admin-border sm:grid-cols-[minmax(0,1fr)_auto]">
                    <p class="text-sm font-medium admin-heading">{{ __('messages.trust_safety.action_labels.'.$event->action) }}</p>
                    <time class="text-xs admin-muted" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                    <p class="text-xs admin-muted sm:col-span-2">{{ $event->actor?->name ?? __('messages.trust_safety.system_actor') }}@if ($event->reason) · {{ $event->reason }}@endif</p>
                </div>
            @empty
                <p class="text-sm admin-muted">{{ __('messages.trust_safety.no_history') }}</p>
            @endforelse
        </section>

        @if (! in_array($report->status, ['dismissed', 'restricted', 'removed'], true))
            <form method="POST" action="{{ route('admin.reports.action', ['report' => $report->id, 'lang' => app()->getLocale()]) }}" class="grid gap-4 border-t pt-5 admin-border sm:grid-cols-2">
                @csrf
                @foreach ($listFilters as $filter => $value)
                    <input type="hidden" name="{{ $filter }}" value="{{ $value }}">
                @endforeach
                <h3 class="text-base font-semibold admin-heading sm:col-span-2">{{ __('messages.trust_safety.report_actions') }}</h3>
                <label class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.trust_safety.action') }}
                    <select name="action" required class="admin-input min-h-10 w-full border px-3 text-sm">
                        @foreach (['investigate', 'dismiss', 'restrict', 'remove'] as $action)
                            <option value="{{ $action }}" @selected(old('action') === $action)>{{ __('messages.trust_safety.'.$action) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.trust_safety.assignee') }}
                    <select name="assignee_id" class="admin-input min-h-10 w-full border px-3 text-sm">
                        <option value="">{{ __('messages.trust_safety.select_admin') }}</option>
                        @foreach ($administrators as $administrator)
                            <option value="{{ $administrator->id }}" @selected((string) old('assignee_id', $report->assigned_to) === (string) $administrator->id)>{{ $administrator->name }}</option>
                        @endforeach
                    </select>
                </label>
                @error('assignee_id')<span class="text-xs text-rose-700 dark:text-rose-300 sm:col-span-2">{{ $message }}</span>@enderror
                <label for="report-reason" class="grid gap-1.5 text-xs font-semibold admin-heading sm:col-span-2">{{ __('messages.trust_safety.moderation_reason_optional') }}
                    <textarea id="report-reason" name="reason" minlength="10" maxlength="2000" rows="3" class="admin-input w-full border px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                </label>
                @error('reason')<span class="text-xs text-rose-700 dark:text-rose-300 sm:col-span-2">{{ $message }}</span>@enderror
                <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold sm:col-span-2">{{ __('messages.trust_safety.save_action') }}</button>
            </form>
        @endif
    </article>
</section>
