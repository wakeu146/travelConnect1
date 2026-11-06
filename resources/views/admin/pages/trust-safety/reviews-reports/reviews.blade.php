<section class="grid gap-6" aria-label="{{ __('messages.admin_nav.pages.reviews_ratings') }}" data-admin-reviews>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <div class="flex flex-col gap-2 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
        <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.trust_safety.reviews_intro') }}</p>
        <p class="shrink-0 text-xs admin-muted">{{ __('messages.trust_safety.results_count', ['count' => $reviews->total()]) }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2">
        <div class="grid min-w-0 gap-1.5">
            <label for="reviews-search" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.search') }}</label>
            <input id="reviews-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.trust_safety.search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="reviews-status" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.status') }}</label>
            <select id="reviews-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all">{{ __('messages.trust_safety.all_statuses') }}</option>
                @foreach (['pending', 'published', 'flagged'] as $status)
                    <option value="{{ $status }}" @selected(request('status', 'all') === $status)>{{ __('messages.trust_safety.status_labels.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-2 sm:col-span-2">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.trust_safety.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => 'reviews_ratings', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">
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

    @forelse ($reviews as $review)
        @php
            $reviewStatus = $review->status->value;
            $hasPolicyIndicator = $reviewStatus === 'flagged';
        @endphp
        <article class="admin-empty-state grid gap-5 border p-4 sm:p-5" data-admin-review-id="{{ $review->id }}" data-review-signal-count="{{ (int) ($review->reports_count > 0) + (int) $hasPolicyIndicator }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="grid gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/40 bg-amber-500/10 px-2.5 py-1 text-xs font-bold text-amber-800 dark:text-amber-200">
                            <x-admin.icon name="star" class="h-3.5 w-3.5" />
                            {{ __('messages.trust_safety.rating', ['rating' => $review->rating]) }}
                        </span>
                        <span class="admin-badge rounded-full px-2.5 py-1 text-xs font-semibold">
                            {{ __('messages.trust_safety.status_labels.'.$reviewStatus) }}
                        </span>
                        @if ($review->is_hidden)
                            <span class="rounded-full border border-slate-500/40 bg-slate-500/10 px-2.5 py-1 text-xs font-semibold admin-muted">{{ __('messages.trust_safety.hidden') }}</span>
                        @else
                            <span class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-200">{{ __('messages.trust_safety.visible') }}</span>
                        @endif
                    </div>
                    <p class="text-xs admin-muted">{{ __('messages.trust_safety.created') }} · <time datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('M j, Y · H:i') }}</time></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($review->reports_count > 0)
                        <span data-review-signal="report" class="inline-flex items-center gap-1 rounded-full border border-rose-500/40 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-800 dark:text-rose-200">
                            <x-admin.icon name="flag" class="h-3.5 w-3.5" />
                            {{ __('messages.trust_safety.report_count', ['count' => $review->reports_count]) }}
                        </span>
                    @endif
                    @if ($hasPolicyIndicator)
                        <span data-review-signal="policy" class="rounded-full border border-orange-500/40 bg-orange-500/10 px-2.5 py-1 text-xs font-semibold text-orange-800 dark:text-orange-200">{{ __('messages.trust_safety.policy_indicator') }}</span>
                    @endif
                </div>
            </div>

            <blockquote class="text-sm leading-6 admin-heading">{{ $review->comment }}</blockquote>

            <dl class="grid gap-3 border-t pt-4 text-sm admin-border sm:grid-cols-3">
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.author') }}</dt>
                    <dd class="font-medium admin-heading">{{ $review->user?->name ?? __('messages.trust_safety.unassigned') }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.related_agency') }}</dt>
                    <dd class="font-medium admin-heading">{{ $review->agency?->company_name ?? '—' }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.related_package') }}</dt>
                    <dd class="font-medium admin-heading">{{ $review->booking?->package?->title ?? '—' }}</dd>
                </div>
            </dl>

            <div class="grid gap-4 border-t pt-4 admin-border lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.8fr)]">
                <details class="grid gap-3">
                    <summary class="cursor-pointer text-sm font-semibold admin-heading">{{ __('messages.trust_safety.audit_history') }}</summary>
                    @if ($review->events->isNotEmpty())
                        <ol class="grid gap-3 border-l pl-4">
                            @foreach ($review->events as $event)
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

                <form method="POST" action="{{ route('admin.reviews.moderation', ['review' => $review->id, 'lang' => app()->getLocale()]) }}" class="grid content-start gap-3">
                    @csrf
                    <input type="hidden" name="decision" value="{{ $review->is_hidden ? 'restore' : 'hide' }}">
                    <div class="grid gap-1.5">
                        <label for="review-reason-{{ $review->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.moderation_reason_optional') }}</label>
                        <textarea id="review-reason-{{ $review->id }}" name="reason" minlength="10" maxlength="2000" rows="2" class="admin-input w-full border px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                        @error('reason')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">
                        {{ $review->is_hidden ? __('messages.trust_safety.restore_review') : __('messages.trust_safety.hide_review') }}
                    </button>
                </form>
            </div>
        </article>
    @empty
        <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.trust_safety.no_reviews') }}</p>
    @endforelse

    {{ $reviews->links() }}
</section>
