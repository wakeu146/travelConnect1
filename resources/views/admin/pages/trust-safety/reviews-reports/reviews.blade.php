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

    <div class="admin-empty-state overflow-x-auto border">
        <table class="w-full min-w-[68rem] text-left text-sm">
            <thead class="border-b admin-border">
                <tr class="text-xs uppercase tracking-wide admin-muted">
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.rating_label') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.author') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.related_agency') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.status') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.created') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.admin_users.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y admin-border">
                @forelse ($reviews as $review)
                    @php
                        $reviewStatus = $review->status->value;
                        $hasPolicyIndicator = $reviewStatus === 'flagged';
                    @endphp
                    <tr data-admin-review-id="{{ $review->id }}" data-review-signal-count="{{ (int) ($review->reports_count > 0) + (int) $hasPolicyIndicator }}">
                        <td class="px-4 py-3 font-semibold admin-heading">
                            {{ __('messages.trust_safety.rating', ['rating' => $review->rating]) }}
                            @if ($review->reports_count > 0)
                                <span class="mt-1 block text-xs text-rose-700 dark:text-rose-300" data-review-signal="report">
                                    {{ __('messages.trust_safety.report_count', ['count' => $review->reports_count]) }}
                                </span>
                            @endif
                            @if ($hasPolicyIndicator)
                                <span class="mt-1 block text-xs text-rose-700 dark:text-rose-300" data-review-signal="policy">
                                    {{ __('messages.trust_safety.policy_indicator') }}
                                </span>
                            @endif
                        </td>
                        <td class="max-w-48 truncate px-4 py-3 admin-heading" title="{{ $review->user?->name ?? __('messages.trust_safety.unassigned') }}">{{ $review->user?->name ?? __('messages.trust_safety.unassigned') }}</td>
                        <td class="max-w-56 truncate px-4 py-3 admin-heading" title="{{ $review->agency?->company_name ?? '—' }}">{{ $review->agency?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="admin-badge inline-flex border px-2 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$reviewStatus) }}</span>
                            <span class="mt-1 block text-xs admin-muted">{{ $review->is_hidden ? __('messages.trust_safety.hidden') : __('messages.trust_safety.visible') }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs admin-muted"><time datetime="{{ $review->created_at->toIso8601String() }}">{{ $review->created_at->format('M j, Y · H:i') }}</time></td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.reviews.show', array_filter(['review' => $review->id, 'lang' => app()->getLocale(), 'search' => request('search'), 'status' => request('status')])) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">
                                {{ __('messages.admin_users.view_details') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center admin-muted">{{ __('messages.trust_safety.no_reviews') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $reviews->links() }}
</section>
