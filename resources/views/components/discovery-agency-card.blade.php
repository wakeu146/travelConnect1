@props(['card', 'recent' => false, 'featured' => false])
@php($initials = collect(explode(' ', $card['name']))->filter()->take(2)->map(fn (string $word): string => mb_substr($word, 0, 1))->implode(''))
<article data-discovery-card data-agency-id="{{ $card['id'] }}" class="group {{ $recent ? 'grid grid-cols-[5rem_minmax(0,1fr)] items-center gap-3 rounded-none border border-[var(--account-border)] bg-[var(--account-surface)] p-3 shadow-sm sm:grid-cols-[6rem_minmax(0,1fr)] sm:gap-4 sm:p-4' : 'overflow-hidden rounded-none border border-[var(--account-border)] bg-[var(--account-surface)] shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-[var(--account-shadow)]' }}">
    <div class="{{ $recent ? 'relative h-20 w-20 overflow-hidden rounded-none bg-[var(--account-surface-muted)] sm:h-24 sm:w-24' : 'relative h-44 overflow-hidden bg-[var(--account-surface-muted)] sm:h-48' }}">
        <img
            data-card-cover
            src="{{ $card['cover_url'] ?? '' }}"
            alt=""
            class="{{ $card['cover_url'] ? 'block' : 'hidden' }} h-full w-full object-cover transition duration-500 group-hover:scale-105"
        >
        <div data-card-cover-fallback class="{{ $card['cover_url'] ? 'hidden' : 'flex' }} h-full items-center justify-center bg-gradient-to-br from-[#dcebe8] via-[#edf4f3] to-[#f8e9df] text-[#456574]">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="h-14 w-14" aria-hidden="true">
                <path d="M3 19.5 8.5 14l3.5 3.5 4-5 5 7M4 4.5h16v15H4z" />
                <circle cx="9" cy="9" r="1.5" />
            </svg>
        </div>
        <div class="absolute inset-x-0 bottom-0 {{ $recent ? 'h-12' : 'h-20' }} bg-gradient-to-t from-[#102936]/35 to-transparent"></div>
        <span data-card-verified class="{{ $card['verified'] ? 'inline-flex' : 'hidden' }} absolute {{ $recent ? 'left-2 top-2 gap-1 px-2 py-1 text-[10px]' : 'left-3 top-3 gap-1.5 px-3 py-1.5 text-xs' }} items-center rounded-full bg-white/95 font-bold text-[#173042] shadow-sm">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="{{ $recent ? 'h-3 w-3' : 'h-4 w-4' }} text-[#2f9e7a]" aria-hidden="true">
                <path d="m5 12 4 4L19 6" />
                <circle cx="12" cy="12" r="9" />
            </svg>
            {{ __('messages.agency_discovery.verified') }}
        </span>
        <span data-card-featured class="{{ $featured ? 'inline-flex' : 'hidden' }} absolute right-3 top-3 items-center rounded-full bg-[#173042]/90 px-3 py-1.5 text-xs font-bold text-white shadow-sm">
            {{ __('messages.agency_discovery.featured_badge') }}
        </span>
        <div class="absolute {{ $recent ? 'bottom-2 left-2 h-10 w-10 rounded-none text-xs' : 'bottom-3 left-4 h-14 w-14 rounded-none text-sm' }} flex items-center justify-center overflow-hidden border-2 border-white bg-[var(--account-surface)] font-extrabold text-[var(--account-text)] shadow-md">
            <img
                data-card-logo
                src="{{ $card['logo_url'] ?? '' }}"
                alt=""
                class="{{ $card['logo_url'] ? 'block' : 'hidden' }} h-full w-full object-cover"
            >
            <span data-card-initials class="{{ $card['logo_url'] ? 'hidden' : 'block' }}">{{ $initials }}</span>
        </div>
    </div>

    <div class="{{ $recent ? 'grid min-w-0 content-center gap-2' : 'grid gap-4 p-4 sm:p-5' }}">
        <div class="grid gap-2">
            <h2 data-card-name class="{{ $recent ? 'line-clamp-2 text-sm font-bold text-[var(--account-text)] sm:text-base' : 'line-clamp-1 text-lg font-bold text-[var(--account-text)]' }}">{{ $card['name'] }}</h2>
            <p data-card-location class="{{ $card['location'] ? 'flex' : 'hidden' }} min-w-0 items-center gap-2 {{ $recent ? 'text-xs sm:text-sm' : 'text-sm' }} text-[var(--account-text-secondary)]">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0 text-[var(--account-accent)]" aria-hidden="true">
                    <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" />
                    <circle cx="12" cy="10" r="2.5" />
                </svg>
                <span data-card-location-text class="truncate">{{ $card['location'] }}</span>
            </p>
        </div>

        <div data-card-rating class="{{ $card['rating'] ? 'flex' : 'hidden' }} items-center gap-2 {{ $recent ? 'text-xs' : 'text-sm' }}" @if ($card['rating']) aria-label="{{ trans_choice('messages.agency_discovery.rating_label', $card['review_count'], ['rating' => $card['rating'], 'count' => $card['review_count']]) }}" @endif>
            <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-amber-500" aria-hidden="true">
                <path d="m12 2.8 2.8 5.7 6.3.9-4.6 4.5 1.1 6.3-5.6-3-5.6 3 1.1-6.3-4.6-4.5 6.3-.9L12 2.8Z" />
            </svg>
            <span data-card-rating-value class="font-bold text-[var(--account-text)]">{{ $card['rating'] }}</span>
            <span data-card-review-count class="text-[var(--account-text-secondary)]">{{ $card['review_count'] ? trans_choice('messages.agency_discovery.review_count', $card['review_count'], ['count' => $card['review_count']]) : '' }}</span>
        </div>

        <div data-card-destinations-wrapper class="{{ count($card['destinations']) && ! $recent ? 'grid' : 'hidden' }} gap-2">
            <p class="text-xs font-semibold text-[var(--account-text-secondary)]">{{ __('messages.agency_discovery.destinations') }}</p>
            <div data-card-destinations class="flex flex-wrap gap-1.5">
                @foreach ($card['destinations'] as $destination)
                    <span class="rounded-full bg-[var(--account-surface-muted)] px-2.5 py-1 text-xs font-medium text-[var(--account-text-secondary)]">{{ $destination }}</span>
                @endforeach
            </div>
        </div>
        <div data-card-services-wrapper class="{{ count($card['services']) && ! $recent ? 'grid' : 'hidden' }} gap-2">
            <p class="text-xs font-semibold text-[var(--account-text-secondary)]">{{ __('messages.agency_discovery.services') }}</p>
            <div data-card-services class="flex flex-wrap gap-1.5">
                @foreach ($card['services'] as $service)
                    <span class="rounded-full bg-[var(--account-accent-soft)] px-2.5 py-1 text-xs font-medium text-[var(--account-text)]">{{ $service }}</span>
                @endforeach
            </div>
        </div>

        <div class="{{ $recent ? 'flex items-center gap-2 border-t border-[var(--account-border)] pt-2' : 'grid gap-2 border-t border-[var(--account-border)] pt-4' }}">
            <a data-card-profile href="{{ $card['profile_url'] }}" class="inline-flex min-h-10 min-w-0 flex-1 items-center justify-center rounded-none bg-[var(--account-action-neutral)] px-2 text-center text-xs font-bold text-[var(--account-surface)] transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--account-focus)] sm:text-sm {{ $recent ? 'min-h-9' : 'min-h-11 px-3' }}">
                {{ __('messages.agency_discovery.view_agency') }}
            </a>
            <button
                type="button"
                data-favorite-toggle
                data-agency-slug="{{ $card['slug'] }}"
                data-agency-name="{{ $card['name'] }}"
                data-favorite-url="{{ $card['favorite_url'] }}"
                data-authenticated="true"
                data-saved-label="{{ __('messages.agency_discovery.saved') }}"
                data-unsaved-label="{{ __('messages.agency_discovery.save_agency') }}"
                aria-pressed="{{ $card['saved'] ? 'true' : 'false' }}"
                aria-label="{{ $card['saved'] ? __('messages.agency_discovery.saved') : __('messages.agency_discovery.save_agency') }} {{ $card['name'] }}"
                class="{{ $recent ? 'h-9 w-9 shrink-0' : 'min-h-11' }} inline-flex items-center justify-center gap-1.5 rounded-none border border-[var(--account-border-strong)] px-2 text-center text-xs font-bold text-[var(--account-text-secondary)] transition hover:bg-[var(--account-hover)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--account-focus)] sm:text-sm {{ $card['saved'] ? 'text-[var(--account-accent)]' : '' }}"
            >
                <span data-favorite-icon aria-hidden="true">{{ $card['saved'] ? '♥' : '♡' }}</span>
                <span data-favorite-label class="{{ $recent ? 'sr-only' : '' }}">{{ $card['saved'] ? __('messages.agency_discovery.saved') : __('messages.agency_discovery.save_agency') }}</span>
            </button>
            <button
                type="button"
                data-recent-remove
                data-remove-url="{{ $card['remove_recent_url'] }}"
                aria-label="{{ __('messages.agency_discovery.remove_recent') }} {{ $card['name'] }}"
                class="{{ $recent ? 'inline-flex h-9 w-9 shrink-0' : 'hidden' }} items-center justify-center rounded-none text-[var(--account-text-secondary)] transition hover:bg-[var(--account-hover)] hover:text-[var(--account-danger)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--account-focus)]"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                    <path d="M4 7h16M10 11v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3" />
                </svg>
            </button>
        </div>
    </div>
</article>
