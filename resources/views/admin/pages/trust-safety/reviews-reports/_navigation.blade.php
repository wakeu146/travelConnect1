@php
    $reviewReportPages = [
        'reviews_ratings',
        'reported_content',
        'user_agency_complaints',
    ];
@endphp

<nav class="admin-empty-state flex min-w-0 flex-wrap gap-2 border p-2" aria-label="{{ __('messages.admin_nav.groups.reviews_reports') }}" data-admin-review-report-navigation>
    @foreach ($reviewReportPages as $reviewReportPage)
        <a
            href="{{ route('admin.dashboard.page', ['page' => $reviewReportPage, 'lang' => app()->getLocale()]) }}"
            @if ($activePage === $reviewReportPage) aria-current="page" @endif
            class="admin-nav-link inline-flex min-h-10 min-w-0 items-center break-words border px-3 py-2 text-sm font-semibold {{ $activePage === $reviewReportPage ? 'is-active' : '' }}"
        >
            {{ __('messages.admin_nav.pages.'.$reviewReportPage) }}
        </a>
    @endforeach
</nav>

@if (session('status'))
    <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
        {{ session('status') }}
    </p>
@endif
