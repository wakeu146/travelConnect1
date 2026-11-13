@php
    $agency = $profile['agency'];
    $verificationPages = ['verification_pending', 'approved', 'rejected'];
    $isVerificationWorkflow = in_array($activePage, $verificationPages, true);
    $verificationClass = match ($agency->verification_status->value) {
        'verified' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200',
        'rejected' => 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200',
        default => 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
    };
@endphp

<section class="grid gap-6" aria-label="{{ __('messages.admin_agencies.profile') }}" data-admin-agency-profile>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.dashboard.page', ['page' => $isVerificationWorkflow ? $activePage : 'all_agencies', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-3 py-2 text-sm font-semibold">
            <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
            {{ __('messages.admin_agencies.'.($isVerificationWorkflow ? 'back_to_verification' : 'back_to_directory')) }}
        </a>
        <span class="text-xs admin-muted">{{ __('messages.admin_alerts.source_id', ['id' => $agency->id]) }}</span>
    </div>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    @if (session('warning'))
        <p role="alert" class="border border-amber-500/40 bg-amber-500/10 p-4 text-sm font-semibold text-amber-900 dark:text-amber-200">{{ session('warning') }}</p>
    @endif

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <article class="admin-empty-state grid gap-6 border p-5 sm:p-7">
        <div class="relative h-16 overflow-hidden rounded-lg bg-gradient-to-br from-slate-300 via-slate-200 to-slate-100 dark:from-slate-800 dark:via-slate-900 dark:to-slate-950 sm:h-20" data-agency-cover>
            @if ($coverUrl = $agency->coverImageUrl())
                <img src="{{ $coverUrl }}" alt="{{ __('messages.admin_agencies.cover_alt', ['agency' => $agency->company_name]) }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full items-center justify-center text-slate-400 dark:text-slate-600">
                    <x-admin.icon name="building" class="h-16 w-16" />
                </div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/45 via-transparent to-transparent"></div>
        </div>
        <header class="grid gap-4 border-b pb-5 admin-border sm:flex sm:items-start sm:justify-between">
            <div class="grid min-w-0 gap-3">
                <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:h-16 sm:w-16" data-agency-logo>
                    @if ($logoUrl = $agency->logoUrl())
                        <img src="{{ $logoUrl }}" alt="{{ __('messages.admin_agencies.logo_alt', ['agency' => $agency->company_name]) }}" class="h-full w-full object-cover">
                    @else
                        <x-admin.icon name="building" class="h-10 w-10 text-slate-400 dark:text-slate-500" />
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $verificationClass }}">{{ __('messages.admin_agencies.verification_statuses.'.$agency->verification_status->value) }}</span>
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $agency->operating_status === 'suspended' ? 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200' }}">{{ __('messages.admin_agencies.operating_statuses.'.$agency->operating_status) }}</span>
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold">{{ __('messages.admin_agencies.case_statuses.'.$profile['caseStatus']) }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="break-words text-2xl font-bold admin-heading">{{ $agency->company_name }}</h2>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm" aria-label="{{ __('messages.admin_agencies.agency_rating', ['rating' => number_format($profile['publishedRating'], 1), 'count' => $profile['publishedReviewCount']]) }}" data-agency-rating>
                    <span class="font-bold admin-heading">{{ number_format($profile['publishedRating'], 1) }}/5</span>
                    <span aria-hidden="true" class="tracking-wide text-amber-600 dark:text-amber-300">{{ str_repeat('★', (int) round($profile['publishedRating'])) }}{{ str_repeat('☆', 5 - (int) round($profile['publishedRating'])) }}</span>
                    <span class="text-xs admin-muted">({{ $profile['publishedReviewCount'] }})</span>
                </div>
            </div>
            <div class="grid shrink-0 gap-1 text-sm sm:text-right">
                <span class="admin-muted">{{ __('messages.admin_agencies.created') }}: {{ $agency->created_at->locale(app()->getLocale())->isoFormat('LL') }}</span>
                @if ($agency->verification_status->value === 'verified')
                    <span class="admin-muted">{{ __('messages.admin_agencies.verified_at') }}: {{ $agency->verified_at?->locale(app()->getLocale())->isoFormat('LL') ?? __('messages.admin_agencies.approval_not_recorded') }}</span>
                @endif
            </div>
        </header>

        @if ($profile['verificationDecision'])
            <section class="grid gap-3 border-b pb-5 admin-border" aria-label="{{ __('messages.admin_agencies.decision_details') }}">
                <h3 class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.decision_details') }}</h3>
                <dl class="grid gap-3 sm:grid-cols-3">
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.decision_reviewer') }}</dt>
                        <dd class="text-sm admin-heading">{{ $profile['verificationDecision']->actor?->name ?? __('messages.admin_agencies.unknown_admin') }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.decision_date') }}</dt>
                        <dd class="text-sm admin-heading"><time datetime="{{ $profile['verificationDecision']->created_at->toIso8601String() }}">{{ $profile['verificationDecision']->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time></dd>
                    </div>
                    @if ($profile['verificationDecision']->reason)
                        <div class="grid gap-1 sm:col-span-3">
                            <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.decision_reason') }}</dt>
                            <dd class="whitespace-pre-wrap text-sm admin-heading">{{ $profile['verificationDecision']->reason }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="grid content-start gap-4" aria-labelledby="agency-public-information">
                <h3 id="agency-public-information" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.public_information') }}</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'description' => $agency->description,
                        'license_number' => $agency->license_number,
                        'address' => $agency->address,
                        'phone' => $agency->phone,
                        'email' => $agency->email,
                        'website' => $agency->website,
                        'countries' => $agency->countries->pluck('name')->join(', '),
                        'services' => $agency->services->pluck('name')->join(', '),
                    ] as $field => $value)
                        <div class="grid min-w-0 gap-1 border-b pb-3 admin-border {{ $field === 'description' ? 'sm:col-span-2' : '' }}">
                            <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.'.$field) }}</dt>
                            <dd class="break-words whitespace-pre-wrap text-sm admin-heading">{{ $value ?: __('messages.admin_agencies.not_provided') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="grid content-start gap-4" aria-labelledby="agency-owner-information">
                <h3 id="agency-owner-information" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.owner_information') }}</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.owner') }}</dt>
                        <dd class="text-sm admin-heading">{{ $agency->owner?->name ?? __('messages.admin_alerts.unknown_account') }}</dd>
                    </div>
                    <div class="grid gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.presence') }}</dt>
                        <dd class="text-sm admin-heading">
                            @if ($agency->owner?->isOnline())
                                <span class="inline-flex items-center gap-2 text-xs admin-muted" data-owner-presence="online">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    <span>{{ __('messages.admin_agencies.online_now') }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 text-xs admin-muted" data-owner-presence="offline">
                                    <span class="h-2 w-2 rounded-full bg-slate-400" aria-hidden="true"></span>
                                    <span>{{ __('messages.admin_agencies.offline') }}</span>
                                </span>
                                <span class="block text-xs admin-muted">
                                    @if ($agency->owner?->last_active_at)
                                        {{ __('messages.admin_agencies.last_active', ['time' => $agency->owner->last_active_at->locale(app()->getLocale())->diffForHumans()]) }}
                                    @else
                                        {{ __('messages.admin_agencies.never_active') }}
                                    @endif
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="grid gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_alerts.fields.email') }}</dt>
                        <dd class="break-all text-sm admin-heading">{{ $agency->owner?->email ?? '—' }}</dd>
                    </div>
                    <div class="grid gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_agencies.phone') }}</dt>
                        <dd class="text-sm admin-heading">{{ $agency->owner?->phone ?: __('messages.admin_agencies.not_provided') }}</dd>
                    </div>
                </dl>

                <div class="grid gap-2 border-l-4 border-amber-500/70 bg-amber-500/5 p-4">
                    <h4 class="text-sm font-bold admin-heading">{{ __('messages.admin_agencies.missing_information') }}</h4>
                    <p class="text-xs leading-5 admin-muted">{{ __('messages.admin_agencies.missing_information_help') }}</p>
                    @if ($profile['missingInformation'] === [])
                        <p class="text-sm admin-heading">{{ __('messages.admin_agencies.no_missing_information') }}</p>
                    @else
                        <ul class="grid gap-1 text-sm admin-heading">
                            @foreach ($profile['missingInformation'] as $field)
                                <li>{{ __('messages.admin_agencies.'.$field) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>

        <section class="grid gap-3 border-t pt-5 admin-border" aria-labelledby="agency-required-checks-title">
            <header class="grid gap-1">
                <h3 id="agency-required-checks-title" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.required_checks') }}</h3>
                <p class="text-sm admin-muted">{{ __('messages.admin_agencies.required_checks_help') }}</p>
            </header>
            <ul class="grid gap-3 sm:grid-cols-3">
                @foreach ($profile['requiredDocumentTypes'] as $requiredDocumentType)
                    @php
                        $matchingDocuments = $agency->documents->filter(fn ($document) => $document->type->value === $requiredDocumentType);
                        $bundleDocument = $agency->documents->first(fn ($document) => $document->type->value === 'other');
                        $checkDocument = $bundleDocument && $bundleDocument->status->value !== 'rejected'
                            ? $bundleDocument
                            : ($matchingDocuments->first(fn ($document) => $document->status->value === 'approved')
                            ?? $matchingDocuments->first(fn ($document) => $document->status->value !== 'rejected')
                            ?? $matchingDocuments->first()
                            ?? $bundleDocument);
                        $checkStatus = $checkDocument?->status->value ?? 'missing';
                    @endphp
                    <li class="grid gap-1 border p-3 admin-border">
                        <span class="text-sm font-semibold admin-heading">{{ __('messages.admin_agencies.document_types.'.$requiredDocumentType) }}</span>
                        <span class="text-xs admin-muted" data-required-document-status="{{ $checkStatus }}">{{ __('messages.admin_agencies.required_check_statuses.'.$checkStatus) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="grid gap-3 border-t pt-5 admin-border" aria-labelledby="agency-documents-title">
            <header class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="agency-documents-title" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.documents') }} ({{ $agency->documents->count() }})</h3>
                <p class="text-xs admin-muted">
                    {{ __('messages.admin_agencies.submission_date') }}:
                    {{ $agency->documents->first()?->uploaded_at?->locale(app()->getLocale())->isoFormat('LL') ?? __('messages.admin_agencies.no_submission_date') }}
                </p>
            </header>
            @if ($agency->documents->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[38rem] text-left text-sm">
                        <thead class="border-b admin-border">
                            <tr class="text-xs uppercase tracking-wide admin-muted">
                                <th scope="col" class="px-3 py-2 font-semibold">{{ __('messages.admin_agencies.documents') }}</th>
                                <th scope="col" class="px-3 py-2 font-semibold">{{ __('messages.admin_alerts.status') }}</th>
                                <th scope="col" class="px-3 py-2 font-semibold">{{ __('messages.admin_agencies.submitted') }}</th>
                                <th scope="col" class="px-3 py-2 font-semibold"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y admin-border">
                            @foreach ($agency->documents as $document)
                                <tr>
                                    <td class="px-3 py-3 font-semibold admin-heading">{{ __('messages.admin_agencies.document_types.'.$document->type->value) }}</td>
                                    <td class="px-3 py-3 admin-muted">{{ __('messages.admin_agencies.document_statuses.'.$document->status->value) }}</td>
                                    <td class="px-3 py-3 admin-muted">{{ $document->uploaded_at->locale(app()->getLocale())->isoFormat('LLL') }}</td>
                                    <td class="px-3 py-3 text-right">
                                        <a href="{{ route('admin.agencies.documents.show', ['agency' => $agency->id, 'document' => $document->id, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.admin_agencies.view_document') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm admin-muted">{{ __('messages.admin_agencies.no_documents') }}</p>
            @endif
        </section>

        @if ($agency->verification_status->value === 'pending')
            <section class="grid gap-3 border-t pt-5 admin-border" aria-labelledby="agency-verification-title">
                <div class="grid gap-1">
                    <h3 id="agency-verification-title" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.verification_workflow') }}</h3>
                    <p class="text-sm admin-muted">{{ __('messages.admin_agencies.verification_help') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.agencies.verification', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-2 sm:items-end">
                    @csrf
                    @if ($isVerificationWorkflow)
                        <input type="hidden" name="queue" value="{{ $activePage }}">
                    @endif
                    <label class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.verification_decision') }}
                        <select name="decision" required class="admin-input min-h-10 w-full border px-3 text-sm">
                            <option value="approve" @selected(old('decision') === 'approve')>{{ __('messages.admin_agencies.approve') }}</option>
                            <option value="reject" @selected(old('decision') === 'reject')>{{ __('messages.admin_agencies.reject') }}</option>
                        </select>
                    </label>
                    <label for="verification-reason" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.custom_requests.reason_optional') }}
                        <textarea id="verification-reason" name="reason" rows="2" maxlength="2000" class="admin-input w-full border px-3 py-2 text-sm" placeholder="{{ __('messages.admin_agencies.reason_placeholder') }}">{{ old('reason') }}</textarea>
                    </label>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center justify-center border px-4 py-2 text-sm font-semibold sm:col-span-2">{{ __('messages.custom_requests.save_moderation_action') }}</button>
                </form>
            </section>
        @endif

        <section class="grid gap-4 border-t pt-5 admin-border" aria-labelledby="agency-moderation-title">
            <header class="grid gap-1">
                <h3 id="agency-moderation-title" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.operating_status_title') }}</h3>
                @if ($agency->operating_status === 'suspended')
                    <p class="text-sm admin-muted">{{ __('messages.admin_agencies.suspended_at') }}: {{ $agency->suspended_at?->locale(app()->getLocale())->isoFormat('LLL') }}</p>
                    @if ($agency->suspension_reason)
                        <p class="whitespace-pre-wrap text-sm admin-heading">{{ __('messages.admin_agencies.suspension_reason') }}: {{ $agency->suspension_reason }}</p>
                    @endif
                @else
                    <p class="text-sm admin-muted">{{ __('messages.admin_agencies.suspend_help') }}</p>
                @endif
            </header>
            @if ($agency->operating_status === 'suspended')
                <form method="POST" action="{{ route('admin.agencies.reinstate', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    @csrf
                    <div class="grid gap-1.5">
                        <label for="reinstate-reason" class="text-xs font-semibold admin-heading">{{ __('messages.custom_requests.reason_optional') }}</label>
                        <textarea id="reinstate-reason" name="reason" rows="2" minlength="10" maxlength="2000" class="admin-input w-full border px-3 py-2 text-sm" placeholder="{{ __('messages.admin_agencies.reinstate_reason_placeholder') }}"></textarea>
                    </div>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold transition hover:bg-slate-100 dark:hover:border-slate-500 dark:hover:bg-slate-700">{{ __('messages.admin_agencies.reinstate') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.agencies.suspend', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" onsubmit='return confirm(@json(__('messages.admin_agencies.suspend_confirm')))' class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    @csrf
                    <div class="grid gap-1.5">
                        <label for="suspension-reason" class="text-xs font-semibold admin-heading">{{ __('messages.admin_agencies.suspend_reason_optional') }}</label>
                        <textarea id="suspension-reason" name="reason" rows="2" maxlength="2000" class="admin-input w-full border px-3 py-2 text-sm" placeholder="{{ __('messages.admin_agencies.suspend_reason_placeholder') }}"></textarea>
                    </div>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center border border-amber-600/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-900 transition hover:border-amber-700 hover:bg-amber-500/25 hover:text-amber-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:border-amber-400/50 dark:text-amber-200 dark:hover:border-amber-300 dark:hover:bg-amber-400/25 dark:hover:text-amber-100 dark:focus-visible:outline-amber-300">
                        {{ __('messages.admin_agencies.suspend') }}
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.agencies.destroy', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" onsubmit='return confirm(@json(__('messages.admin_agencies.delete_confirm', ['agency' => $agency->company_name])))' class="grid gap-2 border-t pt-4 admin-border sm:flex sm:items-center sm:justify-between">
                @csrf
                @method('DELETE')
                <p class="max-w-2xl text-sm admin-muted">{{ __('messages.admin_agencies.delete_help') }}</p>
                <button type="submit" class="inline-flex min-h-10 shrink-0 items-center justify-center border border-rose-500/50 bg-rose-500/5 px-4 py-2 text-sm font-semibold text-rose-800 transition hover:border-rose-600 hover:bg-rose-500/15 hover:text-rose-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-600 dark:border-rose-400/50 dark:text-rose-200 dark:hover:border-rose-300 dark:hover:bg-rose-400/20 dark:hover:text-rose-100 dark:focus-visible:outline-rose-300">
                    {{ __('messages.admin_agencies.permanently_delete') }}
                </button>
            </form>
        </section>
    </article>

    <section class="grid gap-6" aria-label="{{ __('messages.admin_agencies.activity_list') }}">
        @foreach ([
            ['key' => 'packages', 'title' => __('messages.admin_agencies.packages'), 'items' => $profile['packages'], 'empty' => 'packages_count'],
            ['key' => 'reviews', 'title' => __('messages.admin_agencies.reviews'), 'items' => $profile['reviews'], 'empty' => 'reviews_count'],
            ['key' => 'bookings', 'title' => __('messages.admin_agencies.bookings'), 'items' => $profile['bookings'], 'empty' => 'bookings_count'],
        ] as $activity)
            <section id="agency-{{ $activity['key'] }}" class="admin-empty-state grid gap-3 border p-5 sm:p-6" aria-labelledby="agency-{{ $activity['key'] }}-title">
                <header class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 id="agency-{{ $activity['key'] }}-title" class="text-base font-bold admin-heading">{{ $activity['title'] }}</h3>
                    <span class="text-xs admin-muted">{{ $agency->{$activity['empty']} }} {{ __('messages.admin_agencies.page_of') }}</span>
                </header>
                @if ($activity['items']->isNotEmpty())
                    <ul class="grid divide-y admin-border">
                        @foreach ($activity['items'] as $item)
                            <li class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto] sm:items-center">
                                @if ($activity['key'] === 'packages')
                                    <span class="text-sm font-semibold admin-heading">{{ $item->title }}</span>
                                    <span class="text-xs admin-muted">{{ $item->status->value }} · {{ __('messages.admin_agencies.bookings') }}: {{ $item->bookings_count }}</span>
                                @elseif ($activity['key'] === 'reviews')
                                    <span class="text-sm font-semibold admin-heading">{{ $item->rating }}/5 · {{ $item->comment }}</span>
                                    <span class="text-xs admin-muted">{{ $item->user?->name ?? '—' }} · {{ $item->status->value }} · {{ $item->created_at->locale(app()->getLocale())->isoFormat('LL') }}</span>
                                @else
                                    <span class="text-sm font-semibold admin-heading">{{ $item->booking_reference }} · {{ $item->package?->title ?? '—' }}</span>
                                    <span class="text-xs admin-muted">{{ $item->traveler?->name ?? '—' }} · {{ $item->status->value }} · {{ $item->created_at->locale(app()->getLocale())->isoFormat('LL') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    {{ $activity['items']->links() }}
                @else
                    <p class="text-sm admin-muted">{{ __('messages.admin_agencies.empty_description') }}</p>
                @endif
            </section>
        @endforeach
    </section>

    <section class="admin-empty-state grid gap-4 border p-5 sm:p-6" aria-labelledby="agency-history-title">
        <header class="grid gap-1">
            <h3 id="agency-history-title" class="text-base font-bold admin-heading">{{ __('messages.admin_agencies.management_history') }}</h3>
            <p class="text-sm leading-5 admin-muted">{{ __('messages.admin_agencies.history_help') }}</p>
        </header>
        @if ($profile['history']->isNotEmpty())
            <ol class="grid divide-y admin-border">
                @foreach ($profile['history'] as $event)
                    <li class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto] sm:items-center">
                        <div class="grid gap-1">
                            <p class="text-sm font-semibold admin-heading">{{ __('messages.admin_agencies.actions.'.$event->action) }}</p>
                            <p class="text-xs admin-muted">
                                {{ __('messages.admin_agencies.previous_status') }}:
                                {{ $event->previous_verification_status ?? '—' }} / {{ $event->previous_operating_status ?? '—' }}
                                →
                                {{ __('messages.admin_agencies.new_status') }}:
                                {{ $event->new_verification_status ?? '—' }} / {{ $event->new_operating_status ?? '—' }}
                            </p>
                            @if ($event->reason)
                                <p class="whitespace-pre-wrap text-sm admin-muted">{{ $event->reason }}</p>
                            @endif
                        </div>
                        <p class="text-xs admin-muted">
                            {{ $event->actor?->name ?? __('messages.admin_agencies.unknown_admin') }}
                            · <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                        </p>
                    </li>
                @endforeach
            </ol>
            {{ $profile['history']->links() }}
        @else
            <p class="text-sm admin-muted">{{ __('messages.admin_agencies.no_history') }}</p>
        @endif
    </section>
</section>
