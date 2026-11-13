<section class="grid min-w-0 gap-6" data-admin-booking-details>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.bookings.index', ['lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-3 py-2 text-sm font-semibold">
            <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
            {{ __('messages.admin_bookings.back_to_list') }}
        </a>
        <span class="text-xs admin-muted">{{ $booking->booking_reference }}</span>
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

    <article class="admin-empty-state grid gap-5 border p-5 sm:p-7">
        <header class="flex flex-wrap items-start justify-between gap-4 border-b pb-5 admin-border">
            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-[.14em] admin-accent">{{ __('messages.admin_bookings.booking_overview') }}</p>
                <h2 class="break-all text-2xl font-bold admin-heading">{{ $booking->booking_reference }}</h2>
                <p class="text-sm admin-muted">{{ __('messages.admin_bookings.created') }}: {{ $booking->created_at?->locale(app()->getLocale())->isoFormat('LLL') ?? __('messages.admin_bookings.unavailable') }}</p>
            </div>
            <div class="grid justify-items-start gap-2 sm:justify-items-end">
                <span class="inline-flex border px-3 py-1 text-sm font-semibold">{{ __('messages.admin_bookings.statuses.'.$booking->status->value) }}</span>
                <span class="text-sm admin-muted">{{ __('messages.admin_bookings.payment_status') }}: {{ __('messages.admin_bookings.payment_statuses.'.$booking->payment_status->value) }}</span>
            </div>
        </header>

        <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="grid gap-1 border-b pb-3 admin-border">
                <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.expiry') }}</dt>
                <dd class="text-sm admin-heading">{{ $booking->expires_at?->locale(app()->getLocale())->isoFormat('LLL') ?? __('messages.admin_bookings.unavailable') }}</dd>
            </div>
            @foreach ([
                'confirmed' => $booking->confirmed_at,
                'rejected' => $booking->rejected_at,
                'cancelled' => $booking->cancelled_at,
                'completed' => $booking->completed_at,
            ] as $status => $timestamp)
                @if ($timestamp)
                    <div class="grid gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.statuses.'.$status) }}</dt>
                        <dd class="text-sm admin-heading">{{ $timestamp->locale(app()->getLocale())->isoFormat('LLL') }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </article>

    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <section class="admin-empty-state grid content-start gap-4 border p-5" aria-labelledby="admin-booking-traveler">
            <h3 id="admin-booking-traveler" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.traveler_information') }}</h3>
            @if ($booking->traveler)
                <dl class="grid gap-3">
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.traveler') }}</dt>
                        <dd class="text-sm admin-heading">{{ $booking->traveler->name }}</dd>
                    </div>
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.email') }}</dt>
                        <dd class="break-all text-sm admin-heading">{{ $booking->traveler->email }}</dd>
                    </div>
                    <a href="{{ route('admin.users.show', ['userId' => $booking->traveler->id, 'lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold underline">{{ __('messages.admin_bookings.open_traveler') }}</a>
                </dl>
            @else
                <p class="text-sm admin-muted">{{ __('messages.admin_bookings.unavailable') }}</p>
            @endif
        </section>

        <section class="admin-empty-state grid content-start gap-4 border p-5" aria-labelledby="admin-booking-agency">
            <h3 id="admin-booking-agency" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.agency_information') }}</h3>
            @if ($booking->agency)
                <p class="text-sm admin-heading">{{ $booking->agency->company_name }}</p>
                <a href="{{ route('admin.agencies.show', ['agency' => $booking->agency->id, 'lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold underline">{{ __('messages.admin_bookings.open_agency') }}</a>
            @else
                <p class="text-sm admin-muted">{{ __('messages.admin_bookings.unavailable') }}</p>
            @endif
        </section>

        <section class="admin-empty-state grid content-start gap-4 border p-5" aria-labelledby="admin-booking-departure">
            <h3 id="admin-booking-departure" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.package_departure') }}</h3>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.package') }}</dt>
                    <dd class="text-sm admin-heading">{{ $booking->package?->title ?? $booking->customRequest?->title ?? __('messages.admin_bookings.unavailable') }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.departure_date') }}</dt>
                    <dd class="text-sm admin-heading">{{ $booking->customRequest?->travel_date?->locale(app()->getLocale())->isoFormat('LL') ?? $booking->availability?->departure_date?->locale(app()->getLocale())->isoFormat('LL') ?? __('messages.admin_bookings.unavailable') }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.travelers') }}</dt>
                    <dd class="text-sm admin-heading">{{ $booking->traveler_count }}</dd>
                </div>
                @if ($capacitySnapshot)
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.capacity_remaining') }}</dt>
                        <dd class="text-sm admin-heading">{{ $capacitySnapshot['remaining_capacity'] }} / {{ $capacitySnapshot['capacity'] }}</dd>
                    </div>
                @endif
            </dl>
            @if ($booking->package)
                <a href="{{ route('admin.packages.show', ['package' => $booking->package->id, 'lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold underline">{{ __('messages.admin_bookings.open_package') }}</a>
            @elseif ($booking->customRequest)
                <a href="{{ route('admin.custom-requests.show', ['travelRequest' => $booking->customRequest->id, 'lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold underline">{{ __('messages.custom_requests.view_details') }}</a>
                <p class="whitespace-pre-line text-sm admin-muted">{{ collect($booking->quote_snapshot['services_included'] ?? [])->join(', ') }}</p>
            @endif
        </section>

        <section class="admin-empty-state grid content-start gap-4 border p-5" aria-labelledby="admin-booking-pricing">
            <h3 id="admin-booking-pricing" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.price_snapshot') }}</h3>
            <dl class="grid gap-3 sm:grid-cols-3">
                @if ($booking->booking_type === 'custom')
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.quote_total') }}</dt>
                        <dd class="text-sm admin-heading">{{ data_get($booking->quote_snapshot, 'price', $booking->total_amount) }} {{ $booking->currency }}</dd>
                        <p class="text-xs admin-muted">{{ __('messages.admin_bookings.quote_total_context', ['count' => $booking->traveler_count]) }}</p>
                    </div>
                @else
                    <div class="grid gap-1">
                        <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.unit_price') }}</dt>
                        <dd class="text-sm admin-heading">{{ $booking->unit_price }} {{ $booking->currency }}</dd>
                    </div>
                @endif
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.travelers') }}</dt>
                    <dd class="text-sm admin-heading">{{ $booking->traveler_count }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_bookings.total_price') }}</dt>
                    <dd class="text-sm font-bold admin-heading">{{ $booking->total_amount }} {{ $booking->currency }}</dd>
                </div>
            </dl>
            <p class="text-xs admin-muted">{{ __('messages.admin_bookings.snapshot_notice') }}</p>
        </section>
    </div>

    <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="admin-booking-operations">
        <h3 id="admin-booking-operations" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.operational_actions') }}</h3>
        @php
            $availableActions = match ($booking->status->value) {
                'pending' => ['confirmed', 'rejected', 'cancelled'],
                'confirmed' => ['cancelled', 'completed'],
                default => [],
            };
        @endphp
        @if ($availableActions !== [])
            <form method="POST" action="{{ route('admin.bookings.status', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border p-4 admin-border" data-confirm-transition="{{ __('messages.admin_bookings.confirm_transition', ['status' => ':status']) }}" onsubmit="return confirm(this.dataset.confirmTransition.replace(':status', this.elements.status.selectedOptions[0].textContent))">
                @csrf
                @method('PATCH')
                <label for="booking-transition-status" class="grid gap-1.5 text-sm font-semibold admin-heading">
                    {{ __('messages.admin_bookings.operational_action') }}
                    <select id="booking-transition-status" name="status" required class="admin-input min-h-10 w-full border px-3 text-sm font-normal">
                        @foreach ($availableActions as $nextStatus)
                            <option value="{{ $nextStatus }}" @selected(old('status', $availableActions[0]) === $nextStatus)>{{ __('messages.admin_bookings.statuses.'.$nextStatus) }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="booking-transition-reason" class="grid gap-1.5 text-sm font-semibold admin-heading">
                    {{ __('messages.admin_bookings.reason_optional') }}
                    <textarea id="booking-transition-reason" name="reason" maxlength="1000" rows="3" class="admin-input w-full border p-3 text-sm font-normal" placeholder="{{ __('messages.admin_bookings.reason_placeholder') }}">{{ old('reason') }}</textarea>
                </label>
                <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_bookings.apply_action') }}</button>
            </form>
        @else
            <p class="text-sm admin-muted">{{ __('messages.admin_bookings.no_actions') }}</p>
        @endif
        <form method="POST" action="{{ route('admin.bookings.notes.store', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border p-4 admin-border">
            @csrf
            <label for="booking-note" class="text-sm font-semibold admin-heading">{{ __('messages.admin_bookings.add_internal_note') }}</label>
            <textarea id="booking-note" name="note" minlength="3" maxlength="3000" required rows="3" class="admin-input w-full border p-3 text-sm" placeholder="{{ __('messages.admin_bookings.note_placeholder') }}">{{ old('note') }}</textarea>
            <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_bookings.save_note') }}</button>
        </form>
    </section>

    <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="admin-booking-dispute-open">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="admin-booking-dispute-open" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.disputes') }}</h3>
            <a href="{{ route(config('admin_dashboard.page_routes.booking_disputes'), ['lang' => app()->getLocale(), 'search' => $booking->booking_reference]) }}" class="text-sm font-semibold underline">{{ __('messages.admin_bookings.view_disputes') }}</a>
        </div>
        @forelse ($booking->travelerIssues as $issue)
            <a href="{{ route('admin.booking-disputes.show', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="grid gap-1 border p-3 admin-border">
                <span class="font-semibold admin-heading">{{ $issue->subject }}</span>
                <span class="text-xs admin-muted">{{ __('messages.admin_disputes.statuses.'.$issue->status) }} · {{ __('messages.admin_disputes.priority_labels.'.$issue->priority) }}</span>
            </a>
        @empty
            <p class="text-sm admin-muted">{{ __('messages.admin_disputes.no_cases_for_booking') }}</p>
        @endforelse
        <form method="POST" action="{{ route('admin.bookings.disputes.store', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border p-4 admin-border">
            @csrf
            <h4 class="text-sm font-bold admin-heading">{{ __('messages.admin_disputes.open_case') }}</h4>
            <label class="grid gap-1.5 text-sm font-semibold admin-heading">
                {{ __('messages.admin_disputes.subject') }}
                <input name="subject" required minlength="5" maxlength="160" value="{{ old('subject') }}" class="admin-input min-h-10 w-full border px-3 text-sm font-normal">
            </label>
            <label class="grid gap-1.5 text-sm font-semibold admin-heading">
                {{ __('messages.admin_disputes.reason') }}
                <textarea name="reason" required minlength="10" maxlength="5000" rows="4" class="admin-input w-full border p-3 text-sm font-normal">{{ old('reason') }}</textarea>
            </label>
            <label class="grid max-w-xs gap-1.5 text-sm font-semibold admin-heading">
                {{ __('messages.admin_disputes.category') }}
                <select name="category" class="admin-input min-h-10 w-full border px-3 text-sm font-normal">
                    @foreach (['booking', 'payment', 'departure', 'service', 'other'] as $category)
                        <option value="{{ $category }}" @selected(old('category', 'booking') === $category)>{{ __('messages.admin_disputes.categories.'.$category) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid max-w-xs gap-1.5 text-sm font-semibold admin-heading">
                {{ __('messages.admin_disputes.priority') }}
                <select name="priority" class="admin-input min-h-10 w-full border px-3 text-sm font-normal">
                    @foreach (['low', 'normal', 'high', 'urgent'] as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ __('messages.admin_disputes.priority_labels.'.$priority) }}</option>
                    @endforeach
                </select>
            </label>
            <p class="text-xs admin-muted">{{ __('messages.admin_disputes.admin_opened_notice') }}</p>
            <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_disputes.open_case') }}</button>
        </form>
    </section>

    <section class="admin-empty-state grid gap-4 border p-5" aria-labelledby="admin-booking-timeline">
        <h3 id="admin-booking-timeline" class="text-base font-bold admin-heading">{{ __('messages.admin_bookings.lifecycle_timeline') }}</h3>
        @foreach ($timeline as $event)
            <article class="grid gap-1 border-l-2 pl-4 admin-border">
                @php
                    $eventType = data_get($event, 'event_type');
                    $eventStatus = data_get($event, 'to_status');
                @endphp
                <p class="text-sm font-semibold admin-heading">
                    @if ($eventType === 'created')
                        {{ __('messages.admin_bookings.event_created') }}
                    @elseif ($eventType === 'admin_note')
                        {{ __('messages.admin_bookings.event_admin_note') }}
                    @elseif ($eventType === 'legacy_status')
                        {{ __('messages.admin_bookings.statuses.'.$eventStatus) }}
                    @else
                        {{ __('messages.admin_bookings.event_transition', ['status' => __('messages.admin_bookings.statuses.'.($eventStatus ?? 'pending'))]) }}
                    @endif
                </p>
                <p class="text-xs admin-muted">
                    {{ data_get($event, 'created_at')?->locale(app()->getLocale())->isoFormat('LLL') }}
                    @if (data_get($event, 'actor.name'))
                        · {{ data_get($event, 'actor.name') }}
                    @else
                        · {{ __('messages.admin_bookings.actor_unavailable') }}
                    @endif
                </p>
                @if (data_get($event, 'reason'))
                    <p class="whitespace-pre-wrap break-words text-sm admin-muted">{{ data_get($event, 'reason') }}</p>
                @endif
            </article>
        @endforeach
    </section>
</section>
