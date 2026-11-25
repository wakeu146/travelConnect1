<section class="grid gap-6" data-admin-user-details>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ $backUrl }}" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-3 py-2 text-sm font-semibold">
            <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
            {{ __('messages.admin_users.back_to_list') }}
        </a>
        <span class="text-xs admin-muted">{{ __('messages.admin_alerts.source_id', ['id' => $userDetails->id]) }}</span>
    </div>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="grid gap-1 border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <article class="admin-empty-state grid gap-6 border p-5 sm:p-7" data-admin-user-profile>
        <header class="grid gap-4 border-b pb-5 admin-border sm:flex sm:items-start sm:justify-between">
            <div class="grid min-w-0 gap-3">
                <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-600 dark:bg-slate-800 sm:h-16 sm:w-16" data-user-profile-photo>
                    @if ($userDetails->profile_photo_path)
                        <img src="{{ $userDetails->profilePhotoUrl() }}" alt="{{ __('messages.admin_users.profile_photo_alt', ['name' => $userDetails->name]) }}" class="h-full w-full object-cover">
                    @else
                        <span class="text-2xl font-bold text-slate-500 dark:text-slate-300" aria-hidden="true">{{ mb_substr($userDetails->name, 0, 1) }}</span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold">{{ __('messages.admin_users.roles.'.$userDetails->role->value) }}</span>
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $userDetails->deleted_at ? 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200' }}">
                        {{ $userDetails->deleted_at ? __('messages.admin_users.statuses.deactivated') : __('messages.admin_users.statuses.active') }}
                    </span>
                    @php
                        $isUserOnline = ! $userDetails->deleted_at && $userDetails->isOnline();
                    @endphp
                    <span class="inline-flex items-center gap-2 text-xs admin-muted" data-user-presence="{{ $isUserOnline ? 'online' : 'offline' }}">
                        <span class="h-2 w-2 rounded-full {{ $isUserOnline ? 'bg-emerald-500' : 'bg-slate-400' }}" aria-hidden="true"></span>
                        <span>{{ $isUserOnline ? __('messages.admin_users.online_now') : __('messages.admin_users.offline') }}</span>
                    </span>
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $userDetails->email_verified_at ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200' : 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200' }}">
                        {{ $userDetails->email_verified_at ? __('messages.admin_users.email_verified') : __('messages.admin_users.email_unverified') }}
                    </span>
                </div>
                <h2 id="user-profile-heading" class="break-words text-2xl font-bold admin-heading">{{ $userDetails->name }}</h2>
                <div class="grid gap-1 text-sm sm:grid-cols-2">
                    <span class="break-all admin-muted">{{ $userDetails->email }}</span>
                    <span class="admin-muted">{{ $userDetails->phone ?: __('messages.admin_users.unavailable') }}</span>
                </div>
            </div>
            <div class="grid shrink-0 gap-1 text-sm sm:text-right">
                <span class="admin-muted">{{ __('messages.admin_users.registered') }}: {{ $userDetails->created_at?->locale(app()->getLocale())->isoFormat('LL') ?? __('messages.admin_users.unavailable') }}</span>
                @if ($userDetails->email_verified_at)
                    <span class="admin-muted">{{ __('messages.admin_users.email_verified_at') }}: {{ $userDetails->email_verified_at->locale(app()->getLocale())->isoFormat('LL') }}</span>
                @endif
                @if ($userDetails->deleted_at)
                    <span class="admin-muted">{{ __('messages.admin_users.deactivated_at') }}: {{ $userDetails->deleted_at->locale(app()->getLocale())->isoFormat('LL') }}</span>
                @endif
                @if (! $isUserOnline)
                    <span class="admin-muted">
                        @if ($userDetails->last_active_at)
                            {{ __('messages.admin_users.last_active', ['time' => $userDetails->last_active_at->locale(app()->getLocale())->diffForHumans()]) }}
                        @else
                            {{ __('messages.admin_users.never_active') }}
                        @endif
                    </span>
                @endif
            </div>
        </header>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="grid content-start gap-4" aria-labelledby="user-account-information">
                <h3 id="user-account-information" class="text-base font-bold admin-heading">{{ __('messages.admin_users.account_information') }}</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'name' => $userDetails->name,
                        'email' => $userDetails->email,
                        'phone' => $userDetails->phone,
                        'role' => __('messages.admin_users.roles.'.$userDetails->role->value),
                        'registered' => $userDetails->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                        'email_verified_at' => $userDetails->email_verified_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    ] as $field => $value)
                        <div class="grid min-w-0 gap-1 border-b pb-3 admin-border">
                            <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.'.$field) }}</dt>
                            <dd class="break-words whitespace-pre-wrap text-sm admin-heading">{{ $value ?: __('messages.admin_users.unavailable') }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="grid content-start gap-4" aria-labelledby="user-account-status">
                <h3 id="user-account-status" class="text-base font-bold admin-heading">{{ __('messages.admin_users.account_status_information') }}</h3>
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.account_status') }}</dt>
                        <dd class="text-sm admin-heading">{{ $userDetails->deleted_at ? __('messages.admin_users.statuses.deactivated') : __('messages.admin_users.statuses.active') }}</dd>
                    </div>
                    <div class="grid min-w-0 gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.email_verification') }}</dt>
                        <dd class="text-sm admin-heading">{{ $userDetails->email_verified_at ? __('messages.admin_users.email_verified') : __('messages.admin_users.email_unverified') }}</dd>
                    </div>
                    @if ($userDetails->deleted_at)
                        <div class="grid min-w-0 gap-1 border-b pb-3 admin-border">
                            <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.deactivated_at') }}</dt>
                            <dd class="text-sm admin-heading">{{ $userDetails->deleted_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd>
                        </div>
                    @endif
                </dl>
                @if ($userDetails->deleted_at && ! $userDetails->adminActivities->contains('action', 'deactivated'))
                    <div class="grid gap-2 border-l-4 border-amber-500/70 bg-amber-500/5 p-4">
                        <h4 class="text-sm font-bold admin-heading">{{ __('messages.admin_users.deactivation_note_title') }}</h4>
                        <p class="text-xs leading-5 admin-muted">{{ __('messages.admin_users.no_deactivation_audit') }}</p>
                    </div>
                @endif
            </section>
        </div>

        @if ($userDetails->role !== \App\Enums\UserRole::ADMIN)
            <section class="grid gap-4 border-t pt-5 admin-border" aria-labelledby="account-actions-heading">
                <div class="grid gap-1">
                    <h2 id="account-actions-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_users.account_actions') }}</h2>
                    <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.account_actions_description') }}</p>
                </div>
                @if ($userDetails->deleted_at)
                    <form method="POST" action="{{ route('admin.users.restore', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_auto]" onsubmit="return confirm('{{ __('messages.admin_users.confirm_restore') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                        <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center self-end border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_users.restore_account') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.users.revoke-sessions', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border-b pb-4 admin-border sm:grid-cols-[1fr_auto]" onsubmit="return confirm('{{ __('messages.admin_users.confirm_revoke_sessions') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                        <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center self-end border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_users.revoke_sessions') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.users.deactivate', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_auto]" onsubmit="return confirm('{{ __('messages.admin_users.confirm_deactivate') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                        <button type="submit" class="inline-flex min-h-10 items-center justify-center border border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-900 focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-amber-200">{{ __('messages.admin_users.deactivate_account') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.users.password-reset', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="flex flex-wrap items-center justify-between gap-3 border-t pt-4 admin-border" onsubmit="return confirm('{{ __('messages.admin_users.confirm_password_reset') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                    <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.password_reset_description') }}</p>
                        <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_users.send_password_reset') }}</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="grid gap-4 border-t pt-5 admin-border" aria-labelledby="admin-notes-heading">
            <div class="grid gap-1">
                <h2 id="admin-notes-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_users.internal_notes') }}</h2>
                <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.internal_notes_description') }}</p>
            </div>
            <details class="grid gap-3">
                <summary class="w-fit cursor-pointer text-sm font-semibold underline underline-offset-4 admin-heading">{{ __('messages.admin_users.add_note') }}</summary>
                <form method="POST" action="{{ route('admin.users.notes.store', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                    <label for="admin-user-note" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.add_note') }}</label>
                    <textarea id="admin-user-note" name="note" required minlength="3" maxlength="4000" rows="4" class="admin-input w-full border px-3 py-2 text-sm" placeholder="{{ __('messages.admin_users.private_note_placeholder') }}">{{ old('note') }}</textarea>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_users.save_note') }}</button>
                </form>
            </details>
        </section>
    </article>

    @if ($userDetails->role === \App\Enums\UserRole::AGENCY_OWNER)
        <section class="admin-empty-state grid gap-4 border p-5 sm:p-6" aria-labelledby="user-agencies-heading">
            <h2 id="user-agencies-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_users.agency_details') }}</h2>
            @if ($userDetails->agency)
                <div class="grid gap-2 border-b pb-3 admin-border">
                    <h3 class="font-semibold admin-heading">{{ $userDetails->agency->company_name }}</h3>
                    <p class="text-sm admin-muted">{{ __('messages.admin_users.verification_status') }}: {{ __('messages.admin_users.verification.'.$userDetails->agency->verification_status->value) }}</p>
                    <p class="text-sm admin-muted">{{ __('messages.admin_users.agency_status') }}: {{ $userDetails->agency->deleted_at ? __('messages.admin_users.agency_statuses.deactivated') : __('messages.admin_users.agency_statuses.active') }}</p>
                </div>
            @else
                <p class="admin-empty-state border p-4 text-sm admin-muted">{{ __('messages.admin_users.no_agency') }}</p>
            @endif
        </section>
    @endif

    @if ($userDetails->role === \App\Enums\UserRole::TRAVELER)
        <section class="grid gap-4" aria-labelledby="traveler-records-heading">
            <h2 id="traveler-records-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_users.traveler_activity') }}</h2>
            @foreach ([
                ['title' => 'bookings', 'records' => $userDetails->bookings, 'empty' => 'no_bookings'],
                ['title' => 'travel_requests', 'records' => $userDetails->travelRequests, 'empty' => 'no_travel_requests'],
                ['title' => 'inquiries', 'records' => $userDetails->inquiries, 'empty' => 'no_inquiries'],
                ['title' => 'reviews', 'records' => $userDetails->reviews, 'empty' => 'no_reviews'],
            ] as $activitySection)
                <section class="admin-empty-state grid gap-3 border p-5 sm:p-6" aria-labelledby="traveler-{{ $activitySection['title'] }}">
                    <h3 id="traveler-{{ $activitySection['title'] }}" class="text-base font-bold admin-heading">{{ __('messages.admin_users.'.$activitySection['title']) }}</h3>
                    @if ($activitySection['records']->isEmpty())
                        <p class="text-sm admin-muted">{{ __('messages.admin_users.'.$activitySection['empty']) }}</p>
                    @else
                        <ul class="grid gap-2">
                            @foreach ($activitySection['records'] as $record)
                                <li class="flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-sm admin-border">
                                    <span class="font-medium admin-heading">
                                        @if ($activitySection['title'] === 'bookings')
                                            {{ $record->booking_reference }} · {{ $record->agency?->company_name ?? __('messages.admin_users.unavailable') }}
                                        @elseif ($activitySection['title'] === 'travel_requests')
                                            {{ $record->title }} · {{ $record->destination }}
                                        @elseif ($activitySection['title'] === 'inquiries')
                                            {{ $record->subject }} · {{ $record->agency?->company_name ?? __('messages.admin_users.unavailable') }}
                                        @else
                                            {{ $record->agency?->company_name ?? __('messages.admin_users.unavailable') }} · {{ __('messages.admin_users.rating', ['rating' => $record->rating]) }}
                                        @endif
                                    </span>
                                    <span class="text-xs admin-muted">{{ is_object($record->status) ? $record->status->value : $record->status }} · {{ $record->created_at?->locale(app()->getLocale())->isoFormat('LLL') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </section>
    @endif

    @if ($userDetails->role === \App\Enums\UserRole::ADMIN)
        <section class="admin-empty-state grid gap-3 border p-5 sm:p-6" aria-labelledby="admin-signins-heading">
            <h2 id="admin-signins-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_users.admin_sign_ins') }}</h2>
            @if ($userDetails->adminLoginEvents->isEmpty())
                <p class="text-sm admin-muted">{{ __('messages.admin_users.no_admin_sign_ins') }}</p>
            @else
                <ul class="grid divide-y admin-border">
                    @foreach ($userDetails->adminLoginEvents as $event)
                        <li class="grid gap-2 py-3 text-sm first:pt-0 last:pb-0 sm:flex sm:items-center sm:justify-between">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold">{{ data_get(__('messages.admin_users.login_outcomes'), $event->outcome, __('messages.admin_users.unknown_login_event')) }}</span>
                                @if ($event->failed_step)
                                    <span class="text-xs admin-muted">{{ data_get(__('messages.admin_users.login_steps'), $event->failed_step, __('messages.admin_users.unknown_login_event')) }}</span>
                                @endif
                            </div>
                            <time class="text-xs admin-muted" datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif

        </section>
    @endif

    <section class="admin-empty-state grid gap-3 border p-5 sm:p-6" aria-labelledby="account-history-heading">
        <h2 id="account-history-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_users.account_history') }}</h2>
        @if ($userDetails->adminActivities->isEmpty())
            <p class="text-sm admin-muted">{{ __('messages.admin_users.no_account_history') }}</p>
        @else
            <ol class="grid divide-y admin-border">
                @foreach ($userDetails->adminActivities as $activity)
                    <li class="grid gap-1 py-3 text-sm first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold admin-heading">{{ __('messages.admin_users.activity_actions.'.$activity->action) }}</span>
                            <time class="text-xs admin-muted" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                        </div>
                        <p class="text-xs admin-muted">{{ __('messages.admin_users.activity_by', ['actor' => $activity->actor?->name ?? __('messages.admin_users.unavailable')]) }} · {{ __('messages.admin_users.activity_outcomes.'.$activity->outcome) }}</p>
                        @if ($activity->reason)
                            <p class="leading-6 admin-heading"><span class="font-semibold">{{ __('messages.admin_users.automatic_reason') }}:</span> {{ $activity->reason }}</p>
                        @endif
                        @if ($activity->note)
                            <p class="whitespace-pre-line leading-6 admin-heading">{{ $activity->note }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
            @if ($userDetails->admin_activities_count > $userDetails->adminActivities->count())
                <p class="text-xs admin-muted">{{ __('messages.admin_users.activity_history_truncated', ['count' => $userDetails->adminActivities->count()]) }}</p>
            @endif
        @endif
    </section>
</section>
