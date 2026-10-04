@props(['rating' => 0, 'count' => 0, 'showValue' => true, 'showCount' => true])
@php($normalizedRating = min(5, max(0, (float) $rating)))
<div {{ $attributes->class(['inline-flex flex-wrap items-center gap-2']) }} role="img" aria-label="{{ __('messages.rating_out_of_five', ['rating' => number_format($normalizedRating, 1)]) }}">
    <span class="inline-flex items-center" aria-hidden="true">
        @for ($star = 1; $star <= 5; $star++)
            @php($fillPercent = max(0, min(100, ($normalizedRating - $star + 1) * 100)))
            <span class="relative inline-block leading-none">
                <span class="text-[var(--review-star-outline)]">★</span>
                <span class="absolute inset-y-0 left-0 overflow-hidden text-[var(--review-star)]" style="width: {{ $fillPercent }}%">★</span>
            </span>
        @endfor
    </span>
    @if ($showValue)
        <span class="text-sm font-semibold tabular-nums">{{ number_format($normalizedRating, 1) }}</span>
    @endif
    @if ($showCount)
        <span class="text-xs text-[var(--review-text-muted)]">{{ trans_choice('messages.traveler_ratings', (int) $count, ['count' => (int) $count]) }}</span>
    @endif
</div>