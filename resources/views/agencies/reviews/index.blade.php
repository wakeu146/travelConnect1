<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.reviews')" :title="__('messages.review_management')" :description="__('messages.review_management_intro')" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-agency.panel class="agency-kpi p-5">
            <p class="agency-text-subtle text-sm font-semibold">{{ __('messages.average_rating') }}</p>
            <x-rating-stars class="mt-4" :rating="$averageRating" :count="$publishedReviewCount" />
        </x-agency.panel>
        <x-agency.metric :label="__('messages.published_reviews')" :value="$publishedReviewCount" :note="__('messages.reviews_from_travelers')" />
    </div>

    <x-agency.panel class="mb-6 p-4">
        <form method="GET" action="{{ route('agency.reviews.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="review-status">{{ __('messages.status') }}
                <select id="review-status" name="status" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
                    <option value="">{{ __('messages.all_statuses') }}</option>
                    @foreach (['pending', 'published', 'flagged'] as $reviewState)
                        <option value="{{ $reviewState }}" @selected($status === $reviewState)>{{ __('messages.review_status_'.$reviewState) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="review-rating">{{ __('messages.rating') }}
                <select id="review-rating" name="rating" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
                    <option value="">{{ __('messages.all_ratings') }}</option>
                    @foreach ([5, 4, 3, 2, 1] as $star)
                        <option value="{{ $star }}" @selected((string) $rating === (string) $star)>{{ $star }} {{ __('messages.stars') }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">{{ __('messages.apply_filters') }}</button>
        </form>
    </x-agency.panel>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(260px,.6fr)]">
        <div class="space-y-4">
            @forelse ($reviews as $review)
                @php $reviewStatus = $review->status->value ?? $review->status; @endphp
                <x-agency.panel>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="agency-text-primary font-semibold">{{ $review->user?->name ?? __('messages.traveler') }}</p>
                            <p class="agency-text-subtle mt-1 text-xs">{{ $review->created_at->translatedFormat('d M Y') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-rating-stars :rating="$review->rating" :count="0" :show-count="false" />
                            <x-agency.status-badge :label="__('messages.review_status_'.$reviewStatus)" :tone="$reviewStatus === 'published' ? 'success' : ($reviewStatus === 'flagged' ? 'danger' : 'warning')" />
                        </div>
                    </div>
                    <p class="agency-text-subtle mt-4 whitespace-pre-line text-sm leading-6">{{ $review->comment }}</p>
                    @if ($reviewStatus === 'pending')
                        <form method="POST" action="{{ route('agency.reviews.publish', ['review' => $review->id, 'lang' => app()->getLocale()]) }}" class="mt-4 flex justify-end">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="agency-button-primary rounded-xl px-4 py-2 text-sm font-bold">{{ __('messages.approve_review') }}</button>
                        </form>
                    @endif
                </x-agency.panel>
            @empty
                <x-agency.empty-state :title="__('messages.no_reviews_yet')" :description="__('messages.reviews_empty_hint')" />
            @endforelse
            @if ($reviews->hasPages())
                <div class="pt-2">{{ $reviews->links() }}</div>
            @endif
        </div>

        <x-agency.panel class="h-fit">
            <h2 class="text-base font-bold">{{ __('messages.rating_distribution') }}</h2>
            @php $maxRatingCount = max(1, (int) $ratingCounts->max()); @endphp
            <div class="mt-5 space-y-3">
                @foreach ([5, 4, 3, 2, 1] as $star)
                    @php $count = (int) ($ratingCounts[$star] ?? 0); @endphp
                    <div class="grid grid-cols-[2rem_1fr_2rem] items-center gap-3 text-xs">
                        <span class="agency-text-subtle">{{ $star }} ★</span>
                        <div class="agency-progress-track h-2 overflow-hidden rounded-full"><div class="agency-progress-bar h-full rounded-full" style="width: {{ round($count / $maxRatingCount * 100) }}%"></div></div>
                        <span class="agency-text-subtle text-right tabular-nums">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </x-agency.panel>
    </div>
</x-agency-shell>