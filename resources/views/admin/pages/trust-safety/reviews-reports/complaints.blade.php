<section class="grid gap-6" aria-label="{{ __('messages.admin_nav.pages.user_agency_complaints') }}" data-admin-complaints>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <div class="flex flex-col gap-2 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
        <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.trust_safety.complaints_intro') }}</p>
        <p class="shrink-0 text-xs admin-muted">{{ __('messages.trust_safety.results_count', ['count' => $complaints->total()]) }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2">
        <div class="grid min-w-0 gap-1.5">
            <label for="complaints-search" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.search') }}</label>
            <input id="complaints-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.trust_safety.search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="complaints-status" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.status') }}</label>
            <select id="complaints-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all">{{ __('messages.trust_safety.all_statuses') }}</option>
                @foreach (['open', 'in_review', 'resolved'] as $status)
                    <option value="{{ $status }}" @selected(request('status', 'all') === $status)>{{ __('messages.trust_safety.status_labels.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-2 sm:col-span-2">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.trust_safety.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">
                {{ __('messages.trust_safety.clear_filters') }}
            </a>
        </div>
    </form>

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @forelse ($complaints as $issue)
        <article class="admin-empty-state grid gap-5 border p-4 sm:p-5" data-admin-complaint-id="{{ $issue->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="grid gap-2">
                    <h2 class="text-base font-bold admin-heading">{{ $issue->subject }}</h2>
                    <div class="flex flex-wrap gap-2">
                        <span class="admin-badge rounded-full px-2.5 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$issue->status) }}</span>
                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold admin-muted">{{ __('messages.trust_safety.service_type') }}: {{ str($issue->type)->replace('_', ' ')->title() }}</span>
                    </div>
                </div>
                <p class="text-xs admin-muted">{{ __('messages.trust_safety.created') }} · <time datetime="{{ $issue->created_at->toIso8601String() }}">{{ $issue->created_at->format('M j, Y · H:i') }}</time></p>
            </div>

            <div class="grid gap-4 border-t pt-4 admin-border md:grid-cols-2">
                <section class="grid content-start gap-2" aria-label="{{ __('messages.trust_safety.involved_parties') }}">
                    <h3 class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.involved_parties') }}</h3>
                    <p class="text-sm admin-heading">{{ __('messages.trust_safety.traveler') }}: {{ $issue->traveler?->name ?? '—' }} @if ($issue->traveler?->email)<span class="admin-muted">({{ $issue->traveler->email }})</span>@endif</p>
                    <p class="text-sm admin-heading">{{ __('messages.trust_safety.related_agency_package') }}:
                        {{ $issue->booking?->agency?->company_name ?? '—' }}
                        @if ($issue->booking?->package)
                            · {{ $issue->booking->package->title }}
                        @endif
                    </p>
                    <p class="text-sm admin-heading">{{ __('messages.trust_safety.booking') }}:
                        {{ $issue->booking?->booking_reference ?? __('messages.trust_safety.no_booking') }}
                    </p>
                    <p class="text-sm admin-heading">{{ __('messages.trust_safety.assignee') }}: {{ $issue->assignee?->name ?? __('messages.trust_safety.unassigned') }}</p>
                </section>

                <section class="grid content-start gap-2" aria-label="{{ __('messages.trust_safety.case_details') }}">
                    <h3 class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.case_details') }}</h3>
                    <p class="whitespace-pre-line text-sm leading-6 admin-heading">{{ $issue->description }}</p>
                </section>
            </div>

            <div class="grid gap-4 border-t pt-4 admin-border lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                <section class="grid content-start gap-3" aria-label="{{ __('messages.trust_safety.audit_history') }}">
                    <h3 class="text-sm font-semibold admin-heading">{{ __('messages.trust_safety.audit_history') }}</h3>
                    @if ($issue->events->isNotEmpty())
                        <ol class="grid gap-3 border-l pl-4">
                            @foreach ($issue->events as $event)
                                <li class="grid gap-1 text-sm">
                                    <span class="font-semibold admin-heading">{{ __('messages.trust_safety.action_labels.'.$event->action) }}</span>
                                    <span class="text-xs admin-muted">{{ $event->actor?->name ?? __('messages.trust_safety.system_actor') }} · {{ $event->created_at->format('M j, Y · H:i') }}</span>
                                    @if ($event->reason)
                                        <span class="whitespace-pre-line text-sm leading-5 admin-muted">{{ $event->reason }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-sm admin-muted">{{ __('messages.trust_safety.no_history') }}</p>
                    @endif
                </section>

                @php
                    $isResolved = $issue->status === 'resolved';
                @endphp
                <form method="POST" action="{{ route('admin.complaints.action', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="grid content-start gap-3">
                    @csrf
                    <h3 class="text-sm font-semibold admin-heading">{{ __('messages.trust_safety.case_actions') }}</h3>
                    <div class="grid gap-1.5">
                        <label for="complaint-action-{{ $issue->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.action') }}</label>
                        <select id="complaint-action-{{ $issue->id }}" name="action" required class="admin-input min-h-10 w-full border px-3 text-sm">
                            @if ($isResolved)
                                <option value="reopen">{{ __('messages.trust_safety.reopen') }}</option>
                            @else
                                @foreach (['assign', 'investigate', 'note', 'resolve'] as $action)
                                    <option value="{{ $action }}" @selected(old('action') === $action)>{{ __('messages.trust_safety.'.$action) }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    @if (! $isResolved)
                        <div class="grid gap-1.5">
                            <label for="complaint-assignee-{{ $issue->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.assignee') }}</label>
                            <select id="complaint-assignee-{{ $issue->id }}" name="assignee_id" class="admin-input min-h-10 w-full border px-3 text-sm">
                                <option value="">{{ __('messages.trust_safety.select_admin') }}</option>
                                @foreach ($administrators as $administrator)
                                    <option value="{{ $administrator->id }}" @selected((string) old('assignee_id', $issue->assigned_to) === (string) $administrator->id)>{{ $administrator->name }}</option>
                                @endforeach
                            </select>
                            @error('assignee_id')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                        </div>
                    @endif
                    <div class="grid gap-1.5">
                        <label for="complaint-reason-{{ $issue->id }}" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.case_note') }}</label>
                        <textarea id="complaint-reason-{{ $issue->id }}" name="reason" required minlength="3" maxlength="2000" rows="3" class="admin-input w-full border px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                        @error('reason')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                    </div>
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.trust_safety.save_action') }}</button>
                </form>
            </div>
        </article>
    @empty
        <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.trust_safety.no_complaints') }}</p>
    @endforelse

    {{ $complaints->links() }}
</section>
