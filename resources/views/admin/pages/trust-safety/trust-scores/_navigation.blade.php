@php
    $trustScorePages = [
        'trust_score_overview',
        'agencies_requiring_attention',
    ];
@endphp

<nav class="admin-empty-state grid min-w-0 grid-cols-1 gap-2 border p-2 sm:flex sm:flex-wrap" aria-label="{{ __('messages.admin_nav.groups.trust_scores') }}" data-admin-trust-score-navigation>
    @foreach ($trustScorePages as $trustScorePage)
        <a
            href="{{ route('admin.dashboard.page', ['page' => $trustScorePage, 'lang' => app()->getLocale()]) }}"
            @if ($activePage === $trustScorePage) aria-current="page" @endif
            class="admin-nav-link inline-flex min-h-10 min-w-0 items-center break-words border px-3 py-2 text-sm font-semibold {{ $activePage === $trustScorePage ? 'is-active' : '' }}"
        >
            {{ __('messages.admin_nav.pages.'.$trustScorePage) }}
        </a>
    @endforeach
</nav>
