<section class="grid min-w-0 gap-6" data-admin-booking-dispute-details>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.booking-disputes.index', ['lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-3 py-2 text-sm font-semibold">
            <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
            {{ __('messages.admin_disputes.back_to_cases') }}
        </a>
        <a href="{{ route('admin.bookings.show', ['booking' => $issue->booking_id, 'lang' => app()->getLocale()]) }}" class="text-sm font-semibold underline">{{ __('messages.admin_disputes.open_booking') }}</a>
    </div>
    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="grid gap-1 border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <article class="admin-empty-state grid gap-5 border p-5 sm:p-7">
        <header class="grid gap-3 border-b pb-5 admin-border sm:flex sm:items-start sm:justify-between">
            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-[.14em] admin-accent">{{ __('messages.admin_disputes.case') }} #{{ $issue->id }}</p>
                <h2 class="break-words text-2xl font-bold admin-heading">{{ $issue->subject }}</h2>
                <p class="text-sm admin-muted">{{ __('messages.admin_bookings.reference') }}: {{ $issue->booking->booking_reference }}</p>
                <p class="text-sm admin-muted">{{ __('messages.admin_disputes.category') }}: {{ __('messages.admin_disputes.categories.'.$issue->category) }}</p>
            </div>
            <div class="grid justify-items-start gap-2 sm:justify-items-end">
                <span class="inline-flex border px-3 py-1 text-sm font-semibold">{{ __('messages.admin_disputes.statuses.'.$issue->status) }}</span>
                <span class="text-sm admin-muted">{{ __('messages.admin_disputes.priority_labels.'.$issue->priority) }}</span>
            </div>
        </header>
        <p class="whitespace-pre-wrap break-words text-sm leading-6 admin-heading">{{ $issue->description }}</p>
        <p class="text-xs admin-muted">
            {{ __('messages.admin_disputes.opened') }}: {{ $issue->created_at?->locale(app()->getLocale())->isoFormat('LLL') }}
            @if ($issue->openedByAdmin)
                · {{ __('messages.admin_disputes.opened_by_admin', ['name' => $issue->openedByAdmin->name]) }}
            @endif
        </p>
        @if ($issue->resolution_reason)
            <div class="grid gap-2 border-l-4 border-emerald-500/60 bg-emerald-500/5 p-4">
                <h3 class="text-sm font-bold admin-heading">{{ __('messages.admin_disputes.resolution') }}</h3>
                <p class="whitespace-pre-wrap break-words text-sm admin-muted">{{ $issue->resolution_reason }}</p>
                <p class="text-xs admin-muted">{{ $issue->resolved_at?->locale(app()->getLocale())->isoFormat('LLL') }}</p>
            </div>
        @endif
    </article>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.booking-disputes.assignment', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-5">
            @csrf
            @method('PATCH')
            <label for="dispute-assignment" class="text-sm font-semibold admin-heading">{{ __('messages.admin_disputes.assignee') }}</label>
            <select id="dispute-assignment" name="assigned_admin_id" class="admin-input min-h-10 w-full border px-3 text-sm">
                <option value="">{{ __('messages.admin_disputes.unassigned') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected($issue->assigned_admin_id === $admin->id)>{{ $admin->name }} · {{ $admin->email }}</option>
                @endforeach
            </select>
            <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_disputes.save_assignment') }}</button>
        </form>

        @php
            $availableTransitions = match ($issue->status) {
                'open' => ['in_review', 'dismissed'],
                'in_review' => ['awaiting_information', 'resolved', 'dismissed'],
                'awaiting_information' => ['in_review', 'resolved', 'dismissed'],
                'resolved' => ['in_review'],
                default => [],
            };
        @endphp
        @if ($availableTransitions !== [])
            <form method="POST" action="{{ route('admin.booking-disputes.status', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-5">
                @csrf
                @method('PATCH')
                <label for="dispute-next-status" class="text-sm font-semibold admin-heading">{{ __('messages.admin_disputes.change_status') }}</label>
                <select id="dispute-next-status" name="status" required class="admin-input min-h-10 w-full border px-3 text-sm">
                    @foreach ($availableTransitions as $status)
                        <option value="{{ $status }}">{{ __('messages.admin_disputes.statuses.'.$status) }}</option>
                    @endforeach
                </select>
                <label for="dispute-transition-reason" class="text-sm font-semibold admin-heading">{{ __('messages.admin_disputes.reason') }}</label>
                <textarea id="dispute-transition-reason" name="reason" minlength="10" maxlength="3000" required rows="3" class="admin-input w-full border p-3 text-sm">{{ old('reason') }}</textarea>
                <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_disputes.save_status') }}</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.booking-disputes.notes.store', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-5">
        @csrf
        <h3 class="text-base font-bold admin-heading">{{ __('messages.admin_disputes.internal_notes') }}</h3>
        <p class="text-xs admin-muted">{{ __('messages.admin_disputes.internal_notes_notice') }}</p>
        <label for="dispute-note" class="sr-only">{{ __('messages.admin_disputes.add_note') }}</label>
        <textarea id="dispute-note" name="note" minlength="3" maxlength="3000" required rows="3" class="admin-input w-full border p-3 text-sm" placeholder="{{ __('messages.admin_disputes.note_placeholder') }}">{{ old('note') }}</textarea>
        <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_disputes.add_note') }}</button>
    </form>

    <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="dispute-activity-heading">
        <h3 id="dispute-activity-heading" class="text-base font-bold admin-heading">{{ __('messages.admin_disputes.activity') }}</h3>
        @forelse ($issue->activities->reverse() as $activity)
            <article class="grid gap-1 border-l-2 pl-4 admin-border">
                <p class="text-sm font-semibold admin-heading">{{ __('messages.admin_disputes.events.'.$activity->event_type) }}</p>
                <p class="text-xs admin-muted">
                    {{ $activity->created_at?->locale(app()->getLocale())->isoFormat('LLL') }}
                    @if ($activity->actor)
                        · {{ $activity->actor->name }}
                    @else
                        · {{ __('messages.admin_bookings.actor_unavailable') }}
                    @endif
                </p>
                @if ($activity->from_status || $activity->to_status)
                    <p class="text-xs admin-muted">
                        {{ __('messages.admin_disputes.statuses.'.$activity->from_status) }}
                        →
                        {{ __('messages.admin_disputes.statuses.'.$activity->to_status) }}
                    </p>
                @endif
                @if ($activity->note)
                    <p class="whitespace-pre-wrap break-words text-sm admin-muted">{{ $activity->note }}</p>
                @endif
                @if ($activity->metadata && isset($activity->metadata['assigned_admin_id']))
                    <p class="text-xs admin-muted">{{ __('messages.admin_disputes.assignment_event', ['name' => $admins->firstWhere('id', $activity->metadata['assigned_admin_id'])?->name ?? __('messages.admin_disputes.unassigned')]) }}</p>
                @endif
            </article>
        @empty
            <p class="text-sm admin-muted">{{ __('messages.admin_disputes.no_activity') }}</p>
        @endforelse
    </section>
</section>
