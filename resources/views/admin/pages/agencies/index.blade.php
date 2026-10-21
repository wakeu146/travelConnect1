@php
    $agencyPages = [
        'all_agencies',
        'pending_verification',
        'verified_agencies',
        'unverified_agencies',
        'suspended_agencies',
    ];
@endphp

<section class="grid gap-6" aria-label="{{ __('messages.admin_agencies.title') }}" data-admin-agency-directory>
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_agencies.introduction') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_agencies.result_count', ['count' => $directory['agencies']->total()]) }}</p>
    </header>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    @if (session('warning'))
        <p role="alert" class="border border-amber-500/40 bg-amber-500/10 p-4 text-sm font-semibold text-amber-900 dark:text-amber-200">{{ session('warning') }}</p>
    @endif

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('messages.admin_agencies.tabs') }}">
        @foreach ($agencyPages as $agencyPage)
            <a
                href="{{ route('admin.dashboard.page', ['page' => $agencyPage, 'lang' => app()->getLocale()]) }}"
                @if ($activePage === $agencyPage) aria-current="page" @endif
                class="admin-nav-link inline-flex min-h-10 items-center border px-3 py-2 text-sm font-semibold {{ $activePage === $agencyPage ? 'is-active' : '' }}"
            >
                {{ __('messages.admin_nav.pages.'.$agencyPage) }}
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => $activePage, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="grid min-w-0 gap-1.5 sm:col-span-2">
            <label for="agency-search" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.search') }}</label>
            <input id="agency-search" name="search" value="{{ $directory['filters']['search'] ?? '' }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.admin_agencies.search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="agency-verification-status" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.verification_status') }}</label>
            <select id="agency-verification-status" name="verification_status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all">{{ __('messages.admin_agencies.all_verification_statuses') }}</option>
                @foreach (['pending', 'verified', 'rejected'] as $status)
                    <option value="{{ $status }}" @selected(($directory['filters']['verification_status'] ?? 'all') === $status)>{{ __('messages.admin_agencies.verification_statuses.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="agency-operating-status" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.operating_status') }}</label>
            <select id="agency-operating-status" name="operating_status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all">{{ __('messages.admin_agencies.all_operating_statuses') }}</option>
                @foreach (['active', 'suspended'] as $status)
                    <option value="{{ $status }}" @selected(($directory['filters']['operating_status'] ?? 'all') === $status)>{{ __('messages.admin_agencies.operating_statuses.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="agency-country" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.country') }}</label>
            <select id="agency-country" name="country_id" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="">{{ __('messages.admin_agencies.all_countries') }}</option>
                @foreach ($directory['countries'] as $country)
                    <option value="{{ $country->id }}" @selected((string) ($directory['filters']['country_id'] ?? '') === (string) $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="agency-created-from" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.created_from') }}</label>
            <input id="agency-created-from" name="created_from" value="{{ $directory['filters']['created_from'] ?? '' }}" type="date" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="agency-created-to" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.created_to') }}</label>
            <input id="agency-created-to" name="created_to" value="{{ $directory['filters']['created_to'] ?? '' }}" type="date" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>
        <div class="flex min-w-0 flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.admin_agencies.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => $activePage, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">
                {{ __('messages.admin_agencies.clear_filters') }}
            </a>
        </div>
    </form>

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($directory['agencies']->isNotEmpty())
        <div class="admin-empty-state overflow-x-auto border">
            <table class="w-full min-w-[58rem] text-left text-sm">
                <thead class="border-b admin-border">
                    <tr class="text-xs uppercase tracking-wide admin-muted">
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_agencies.agency') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_agencies.owner') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_agencies.verification_status') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_agencies.operating_status') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_agencies.created') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_agencies.submitted') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">{{ __('messages.admin_agencies.open_profile') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y admin-border">
                    @foreach ($directory['agencies'] as $agency)
                        <tr class="align-top">
                            <td class="px-4 py-4">
                                @php
                                    $agencyCaseStatus = $agency->operating_status === 'suspended'
                                        ? 'suspended'
                                        : match ($agency->verification_status->value) {
                                            'verified' => 'verified',
                                            'rejected' => 'rejected',
                                            default => $agency->documents_count > 0 ? 'pending' : 'never_submitted',
                                        };
                                    $caseStatusClass = match ($agencyCaseStatus) {
                                        'verified' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
                                        'pending' => 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
                                        'rejected', 'suspended' => 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200',
                                        default => 'border-slate-500/40 bg-slate-500/10 text-slate-700 dark:text-slate-200',
                                    };
                                    $verificationStatus = $agency->verification_status->value;
                                    $verificationClass = match ($verificationStatus) {
                                        'verified' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
                                        'rejected' => 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200',
                                        default => 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
                                    };
                                    $verificationCellClass = match ($verificationStatus) {
                                        'verified' => 'bg-emerald-50/80 dark:bg-emerald-950/30',
                                        'rejected' => 'bg-rose-50/80 dark:bg-rose-950/30',
                                        default => 'bg-amber-50/80 dark:bg-amber-950/30',
                                    };
                                    $operatingCellClass = $agency->operating_status === 'suspended'
                                        ? 'bg-rose-50/80 dark:bg-rose-950/30'
                                        : 'bg-emerald-50/80 dark:bg-emerald-950/30';
                                @endphp
                                <div class="grid min-w-0 gap-2">
                                    <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="font-bold admin-heading underline decoration-current/30 underline-offset-4 hover:decoration-current">{{ $agency->company_name }}</a>
                                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $caseStatusClass }}" data-agency-case-status="{{ $agencyCaseStatus }}">{{ __('messages.admin_agencies.case_statuses.'.$agencyCaseStatus) }}</span>
                                    <span class="text-xs admin-muted">{{ __('messages.admin_agencies.document_count', ['count' => $agency->documents_count]) }} · {{ __('messages.admin_agencies.packages') }}: {{ $agency->packages_count }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-semibold admin-heading">{{ $agency->owner?->name ?? __('messages.admin_alerts.unknown_account') }}</p>
                                <p class="mt-1 text-xs admin-muted">{{ $agency->owner?->email ?? '—' }}</p>
                            </td>
                            <td class="px-4 py-4 {{ $verificationCellClass }}" data-agency-verification-cell="{{ $verificationStatus }}">
                                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $verificationClass }}" data-agency-verification-status="{{ $verificationStatus }}">{{ __('messages.admin_agencies.verification_statuses.'.$verificationStatus) }}</span>
                            </td>
                            <td class="px-4 py-4 {{ $operatingCellClass }}" data-agency-operating-cell="{{ $agency->operating_status }}">
                                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $agency->operating_status === 'suspended' ? 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200' }}" data-agency-operating-status="{{ $agency->operating_status }}">
                                    {{ __('messages.admin_agencies.operating_statuses.'.$agency->operating_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <time datetime="{{ $agency->created_at->toIso8601String() }}">{{ $agency->created_at->locale(app()->getLocale())->isoFormat('ll') }}</time>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                @if ($agency->documents_max_uploaded_at)
                                    <time datetime="{{ \Illuminate\Support\Carbon::parse($agency->documents_max_uploaded_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($agency->documents_max_uploaded_at)->locale(app()->getLocale())->isoFormat('ll') }}</time>
                                @else
                                    <span class="admin-muted">{{ __('messages.admin_agencies.no_submission_date') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.admin_agencies.open_profile') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $directory['agencies']->links() }}</div>
    @else
        <section class="admin-empty-state grid justify-items-center gap-2 border p-8 text-center" data-agencies-empty>
            <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.empty_title') }}</h2>
            <p class="max-w-xl text-sm leading-6 admin-muted">{{ __('messages.admin_agencies.empty_description') }}</p>
        </section>
    @endif
</section>
