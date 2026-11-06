<section class="grid gap-6" aria-label="{{ __('messages.admin_nav.groups.reviews_reports') }}" data-admin-review-report-landing>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.trust_safety.module_intro') }}</p>

    <div class="grid gap-4 md:grid-cols-3">
        @foreach (['reviews_ratings', 'reported_content', 'user_agency_complaints'] as $reviewReportPage)
            <a
                href="{{ route('admin.dashboard.page', ['page' => $reviewReportPage, 'lang' => app()->getLocale()]) }}"
                class="admin-empty-state grid min-h-40 content-between gap-5 border p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:hover:border-slate-600"
            >
                <span class="grid gap-2">
                    <span class="text-base font-bold admin-heading">{{ __('messages.admin_nav.pages.'.$reviewReportPage) }}</span>
                    <span class="text-sm leading-6 admin-muted">
                        {{ __('messages.trust_safety.'.match ($reviewReportPage) {
                            'reviews_ratings' => 'reviews_intro',
                            'reported_content' => 'reports_intro',
                            default => 'complaints_intro',
                        }) }}
                    </span>
                </span>
                <span class="text-xs font-semibold admin-accent">{{ __('messages.admin_nav.section_hub_open') }} <span aria-hidden="true">&rarr;</span></span>
            </a>
        @endforeach
    </div>
</section>
