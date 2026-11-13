<section class="grid min-w-0 gap-6" data-admin-review-detail>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <a href="{{ route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => app()->getLocale(), ...$listFilters]) }}" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-3 py-2 text-sm font-semibold">
        <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
        {{ __('messages.trust_safety.back_to_reviews') }}
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
                <p class="text-xs font-bold uppercase tracking-wide admin-muted">{{ __('messages.trust_safety.review_detail_title', ['id' => $review->id]) }}</p>
                <h2 class="text-2xl font-bold admin-heading">{{ __('messages.trust_safety.review_content') }}</h2>
                <time class="text-xs admin-muted" datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
            </div>
            <div class="grid justify-items-start gap-2 sm:justify-items-end">
                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$review->status->value) }}</span>
                <span class="text-sm font-semibold admin-heading">{{ __('messages.trust_safety.rating', ['rating' => $review->rating]) }}</span>
                <span class="text-xs admin-muted">{{ $review->is_hidden ? __('messages.trust_safety.hidden') : __('messages.trust_safety.visible') }}</span>
            </div>
        </header>

        @if ($review->reports->isNotEmpty() || $review->status->value === 'flagged')
            <section class="grid gap-2 border-l-2 border-amber-500 pl-4" aria-label="{{ __('messages.trust_safety.review_signals') }}">
                <h3 class="text-sm font-semibold admin-heading">{{ __('messages.trust_safety.review_signals') }}</h3>
                @if ($review->reports->isNotEmpty())
                    <p class="text-sm admin-muted">{{ __('messages.trust_safety.report_count', ['count' => $review->reports->count()]) }}</p>
                @endif
                @if ($review->status->value === 'flagged')
                    <p class="text-sm admin-muted">{{ __('messages.trust_safety.policy_indicator') }}</p>
                @endif
            </section>
        @endif

        <blockquote class="whitespace-pre-line text-sm leading-7 admin-heading">{{ $review->comment }}</blockquote>

        @if ($review->agency_response)
            <section class="grid gap-2 border-l-2 border-slate-400 pl-4">
                <h3 class="text-sm font-semibold admin-heading">{{ __('messages.trust_safety.agency_response') }}</h3>
                <p class="whitespace-pre-line text-sm leading-6 admin-muted">{{ $review->agency_response }}</p>
                @if ($review->agency_responded_at)
                    <time class="text-xs admin-muted" datetime="{{ $review->agency_responded_at->toIso8601String() }}">{{ $review->agency_responded_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                @endif
            </section>
        @endif

        <dl class="grid gap-4 border-t pt-5 admin-border sm:grid-cols-2">
            <div class="grid gap-1">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.author') }}</dt>
                <dd class="text-sm admin-heading">{{ $review->user?->name ?? __('messages.trust_safety.unassigned') }}</dd>
                @if ($review->user?->email)
                    <dd class="text-xs admin-muted">{{ $review->user->email }}</dd>
                @endif
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.related_agency') }}</dt>
                <dd class="text-sm admin-heading">{{ $review->agency?->company_name ?? '—' }}</dd>
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.related_package') }}</dt>
                <dd class="text-sm admin-heading">{{ $review->booking?->package?->title ?? '—' }}</dd>
            </div>
            <div class="grid gap-1">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.booking') }}</dt>
                <dd class="text-sm admin-heading">{{ $review->booking?->booking_reference ?? __('messages.trust_safety.no_booking') }}</dd>
            </div>
        </dl>

        @if ($review->reports->isNotEmpty())
            <section class="grid gap-3 border-t pt-5 admin-border">
                <h3 class="text-base font-semibold admin-heading">{{ __('messages.trust_safety.related_reports') }}</h3>
                <div class="grid divide-y admin-border">
                    @foreach ($review->reports as $report)
                        <a href="{{ route('admin.reports.show', ['report' => $report->id, 'lang' => app()->getLocale()]) }}" class="grid gap-1 py-3 text-sm hover:bg-slate-500/5 sm:grid-cols-[minmax(0,1fr)_auto]">
                            <span class="font-medium admin-heading">{{ $report->reason }}</span>
                            <span class="text-xs admin-muted">{{ __('messages.trust_safety.status_labels.'.$report->status) }} · {{ $report->reporter?->name ?? '—' }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="grid gap-3 border-t pt-5 admin-border" aria-label="{{ __('messages.trust_safety.audit_history') }}">
            <h3 class="text-base font-semibold admin-heading">{{ __('messages.trust_safety.audit_history') }}</h3>
            @forelse ($review->events as $event)
                <div class="grid gap-1 border-b pb-3 last:border-0 admin-border sm:grid-cols-[minmax(0,1fr)_auto]">
                    <p class="text-sm font-medium admin-heading">{{ __('messages.trust_safety.action_labels.'.$event->action) }}</p>
                    <time class="text-xs admin-muted" datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                    <p class="text-xs admin-muted sm:col-span-2">{{ $event->actor?->name ?? __('messages.trust_safety.system_actor') }}@if ($event->reason) · {{ $event->reason }}@endif</p>
                </div>
            @empty
                <p class="text-sm admin-muted">{{ __('messages.trust_safety.no_history') }}</p>
            @endforelse
        </section>

        <form method="POST" action="{{ route('admin.reviews.moderation', ['review' => $review->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border-t pt-5 admin-border">
            @csrf
            @foreach ($listFilters as $filter => $value)
                <input type="hidden" name="{{ $filter }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="decision" value="{{ $review->is_hidden ? 'restore' : 'hide' }}">
            <h3 class="text-base font-semibold admin-heading">{{ __('messages.trust_safety.review_moderation') }}</h3>
            <label for="review-reason" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.trust_safety.moderation_reason_optional') }}
                <textarea id="review-reason" name="reason" minlength="10" maxlength="2000" rows="3" class="admin-input w-full border px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </label>
            <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">
                {{ $review->is_hidden ? __('messages.trust_safety.restore_review') : __('messages.trust_safety.hide_review') }}
            </button>
        </form>
    </article>
</section>
