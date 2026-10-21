<section class="grid gap-6" data-admin-user-details>
    <a href="{{ $backUrl }}" class="admin-nav-link inline-flex min-h-10 w-fit items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">
        <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
        {{ __('messages.admin_users.back_to_list') }}
    </a>

    @if (session('status'))
        <p role="status" class="admin-empty-state border p-4 text-sm font-semibold">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="admin-empty-state border border-red-300 p-4 text-sm font-semibold text-red-700">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="admin-empty-state grid gap-1 border border-red-300 p-4 text-sm font-semibold text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="user-profile-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-1">
                <h2 id="user-profile-heading" class="text-xl font-bold admin-heading">{{ $userDetails->name }}</h2>
                <p class="text-sm admin-muted">{{ $userDetails->email }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="admin-badge rounded-full px-3 py-1 text-xs font-semibold">{{ __('messages.admin_users.roles.'.$userDetails->role->value) }}</span>
                <span class="admin-badge rounded-full px-3 py-1 text-xs font-semibold">{{ $userDetails->deleted_at ? __('messages.admin_users.statuses.deactivated') : __('messages.admin_users.statuses.active') }}</span>
            </div>
        </div>
        <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
            <div class="grid gap-1"><dt class="font-semibold admin-muted">{{ __('messages.admin_users.phone') }}</dt><dd class="admin-heading">{{ $userDetails->phone ?: __('messages.admin_users.unavailable') }}</dd></div>
            <div class="grid gap-1"><dt class="font-semibold admin-muted">{{ __('messages.admin_users.registered') }}</dt><dd class="admin-heading">{{ $userDetails->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd></div>
            @if ($userDetails->deleted_at)
                <div class="grid gap-1"><dt class="font-semibold admin-muted">{{ __('messages.admin_users.deactivated_at') }}</dt><dd class="admin-heading">{{ $userDetails->deleted_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</dd></div>
            @endif
        </dl>
        @if ($userDetails->deleted_at && ! $userDetails->adminActivities->contains('action', 'deactivated'))
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.no_deactivation_audit') }}</p>
        @endif
    </section>

    @if ($userDetails->role !== \App\Enums\UserRole::ADMIN)
        <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="account-actions-heading">
            <div class="grid gap-1">
                <h2 id="account-actions-heading" class="text-lg font-bold admin-heading">{{ __('messages.admin_users.account_actions') }}</h2>
                <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.account_actions_description') }}</p>
            </div>
            @if ($userDetails->deleted_at)
                <form method="POST" action="{{ route('admin.users.restore', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_auto]" onsubmit="return confirm('{{ __('messages.admin_users.confirm_restore') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                    <div class="grid gap-1.5">
                        <label for="restore-reason" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.action_reason') }}</label>
                        <input id="restore-reason" name="reason" required minlength="5" maxlength="1000" class="admin-input min-h-10 rounded-lg border px-3 text-sm" autocomplete="off">
                    </div>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center self-end rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.restore_account') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.users.revoke-sessions', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border-b pb-4 admin-border sm:grid-cols-[1fr_auto]" onsubmit="return confirm('{{ __('messages.admin_users.confirm_revoke_sessions') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                    <div class="grid gap-1.5">
                        <label for="revoke-sessions-reason" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.action_reason') }}</label>
                        <input id="revoke-sessions-reason" name="reason" required minlength="5" maxlength="1000" class="admin-input min-h-10 rounded-lg border px-3 text-sm" autocomplete="off">
                    </div>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center self-end rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.revoke_sessions') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.users.deactivate', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_auto]" onsubmit="return confirm('{{ __('messages.admin_users.confirm_deactivate') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                    <div class="grid gap-1.5">
                        <label for="deactivate-reason" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.action_reason') }}</label>
                        <input id="deactivate-reason" name="reason" required minlength="5" maxlength="1000" class="admin-input min-h-10 rounded-lg border px-3 text-sm" autocomplete="off">
                    </div>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center self-end rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.deactivate_account') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.users.password-reset', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="flex flex-wrap items-center justify-between gap-3 border-t pt-4 admin-border" onsubmit="return confirm('{{ __('messages.admin_users.confirm_password_reset') }}')">
                    @csrf
                    @foreach ($detailContext as $contextName => $contextValue)
                        <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
                    @endforeach
                    <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.password_reset_description') }}</p>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.send_password_reset') }}</button>
                </form>
            @endif
        </section>
    @endif

    <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="admin-notes-heading">
        <div class="grid gap-1">
            <h2 id="admin-notes-heading" class="text-lg font-bold admin-heading">{{ __('messages.admin_users.internal_notes') }}</h2>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.internal_notes_description') }}</p>
        </div>
        <form method="POST" action="{{ route('admin.users.notes.store', ['userId' => $userDetails->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3">
            @csrf
            @foreach ($detailContext as $contextName => $contextValue)
                <input type="hidden" name="{{ $contextName }}" value="{{ $contextValue }}">
            @endforeach
            <label for="admin-user-note" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.add_note') }}</label>
            <textarea id="admin-user-note" name="note" required minlength="3" maxlength="4000" rows="4" class="admin-input w-full rounded-lg border px-3 py-2 text-sm">{{ old('note') }}</textarea>
            <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center justify-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.save_note') }}</button>
        </form>
    </section>

    @if ($userDetails->role === \App\Enums\UserRole::AGENCY_OWNER)
        <section class="grid gap-3" aria-labelledby="user-agencies-heading">
            <h2 id="user-agencies-heading" class="text-lg font-bold admin-heading">{{ __('messages.admin_users.agency_details') }}</h2>
            @if ($userDetails->agency)
                <div class="admin-empty-state grid gap-2 border p-4">
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
            <h2 id="traveler-records-heading" class="text-lg font-bold admin-heading">{{ __('messages.admin_users.traveler_activity') }}</h2>
            @foreach ([
                ['title' => 'bookings', 'records' => $userDetails->bookings, 'empty' => 'no_bookings'],
                ['title' => 'travel_requests', 'records' => $userDetails->travelRequests, 'empty' => 'no_travel_requests'],
                ['title' => 'inquiries', 'records' => $userDetails->inquiries, 'empty' => 'no_inquiries'],
                ['title' => 'reviews', 'records' => $userDetails->reviews, 'empty' => 'no_reviews'],
            ] as $activitySection)
                <section class="admin-empty-state grid gap-3 border p-4" aria-labelledby="traveler-{{ $activitySection['title'] }}">
                    <h3 id="traveler-{{ $activitySection['title'] }}" class="font-semibold admin-heading">{{ __('messages.admin_users.'.$activitySection['title']) }}</h3>
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
                                    <span class="text-xs admin-muted">{{ is_object($record->status) ? $record->status->value : $record->status }} · {{ $record->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </section>
    @endif

    @if ($userDetails->role === \App\Enums\UserRole::ADMIN)
        <section class="grid gap-3" aria-labelledby="admin-signins-heading">
            <h2 id="admin-signins-heading" class="text-lg font-bold admin-heading">{{ __('messages.admin_users.admin_sign_ins') }}</h2>
            @if ($userDetails->adminLoginEvents->isEmpty())
                <p class="admin-empty-state border p-4 text-sm admin-muted">{{ __('messages.admin_users.no_admin_sign_ins') }}</p>
            @else
                <ul class="admin-empty-state grid gap-2 border p-4">
                    @foreach ($userDetails->adminLoginEvents as $event)
                        <li class="flex flex-wrap justify-between gap-2 border-b pb-2 text-sm admin-border">
                            <span class="admin-heading">{{ $event->outcome }}{{ $event->failed_step ? ' · '.$event->failed_step : '' }}</span>
                            <time class="admin-muted" datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                        </li>
                    @endforeach
                </ul>
            @endif

        </section>
    @endif

    <section class="grid gap-3" aria-labelledby="account-history-heading">
        <h2 id="account-history-heading" class="text-lg font-bold admin-heading">{{ __('messages.admin_users.account_history') }}</h2>
        @if ($userDetails->adminActivities->isEmpty())
            <p class="admin-empty-state border p-4 text-sm admin-muted">{{ __('messages.admin_users.no_account_history') }}</p>
        @else
            <ol class="admin-empty-state grid gap-3 border p-4">
                @foreach ($userDetails->adminActivities as $activity)
                    <li class="grid gap-1 border-b pb-3 text-sm admin-border last:border-b-0 last:pb-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold admin-heading">{{ __('messages.admin_users.activity_actions.'.$activity->action) }}</span>
                            <time class="text-xs admin-muted" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</time>
                        </div>
                        <p class="text-xs admin-muted">{{ __('messages.admin_users.activity_by', ['actor' => $activity->actor?->name ?? __('messages.admin_users.unavailable')]) }} · {{ __('messages.admin_users.activity_outcomes.'.$activity->outcome) }}</p>
                        @if ($activity->reason)
                            <p class="leading-6 admin-heading"><span class="font-semibold">{{ __('messages.admin_users.action_reason') }}:</span> {{ $activity->reason }}</p>
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
