<section class="grid gap-6" data-custom-request-page>
    <div class="flex flex-wrap items-center justify-between gap-3"><a class="admin-nav-link border px-3 py-2 text-sm font-semibold" href="{{ route('admin.custom-requests.index', ['queue' => $queue, 'lang' => app()->getLocale()]) }}">← {{ __('messages.custom_requests.back_to_list') }}</a><span class="text-xs admin-muted">{{ $travelRequest->reference }}</span></div>
    @if (session('status'))<p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="grid gap-1 border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <article class="admin-empty-state grid gap-4 border p-5">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b pb-4 admin-border"><div><p class="text-xs font-bold uppercase tracking-[.14em] admin-accent">{{ __('messages.custom_requests.request_details') }}</p><h2 class="mt-1 text-2xl font-bold admin-heading">{{ $travelRequest->title }}</h2><p class="mt-1 text-sm admin-muted">{{ $travelRequest->reference }} · {{ $travelRequest->origin }} → {{ $travelRequest->destination }}</p></div><span class="admin-badge border px-3 py-1 text-sm">{{ __('messages.custom_requests.statuses.'.$travelRequest->status) }}</span></header>
        <div class="grid gap-5">
            <dl class="grid gap-4 border-b pb-5 text-sm admin-border sm:grid-cols-2 xl:grid-cols-4">
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.traveler') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->traveler?->name }}</dd><dd class="text-xs admin-muted">{{ $travelRequest->traveler?->email }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.origin') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->origin }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.destination') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->destination }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.destination_country') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->destination_country ?: __('messages.custom_requests.not_specified') }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.departure') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->travel_date?->format('M j, Y') ?? __('messages.custom_requests.not_specified') }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.return') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->return_date?->format('M j, Y') ?? __('messages.custom_requests.not_specified') }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.flexible_dates_status') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->flexible_dates ? __('messages.custom_requests.flexible_dates') : __('messages.custom_requests.fixed_dates') }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.traveler_count') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ trans_choice('messages.custom_requests.travelers', $travelRequest->traveler_count, ['count' => $travelRequest->traveler_count]) }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.purpose') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->purpose ?: __('messages.custom_requests.not_specified') }}</dd></div>
                <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.published') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->published_at?->format('M j, Y H:i') ?? __('messages.custom_requests.not_specified') }}</dd></div>
            </dl>
            <section class="grid gap-2">
                <h3 class="text-sm font-bold admin-heading">{{ __('messages.custom_requests.description') }}</h3>
                <p class="whitespace-pre-line text-sm leading-6 admin-muted">{{ $travelRequest->description }}</p>
            </section>
            <section class="grid gap-2">
                <h3 class="text-sm font-bold admin-heading">{{ __('messages.custom_requests.services') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($travelRequest->services as $service)
                        <span class="admin-badge border px-3 py-1.5 text-sm">{{ $service->name }}</span>
                    @endforeach
                </div>
            </section>
            <section class="grid gap-3 border-t pt-5 admin-border sm:grid-cols-2">
                <div class="grid content-start gap-2">
                    <h3 class="text-sm font-bold admin-heading">{{ __('messages.custom_requests.budget') }}</h3>
                    <dl class="grid gap-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.minimum_budget') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->budget_min !== null ? number_format((float) $travelRequest->budget_min, 2).' '.$travelRequest->budget_currency : __('messages.custom_requests.not_specified') }}</dd></div>
                        <div><dt class="text-xs admin-muted">{{ __('messages.custom_requests.maximum_budget') }}</dt><dd class="mt-1 font-semibold admin-heading">{{ $travelRequest->budget_max !== null ? number_format((float) $travelRequest->budget_max, 2).' '.$travelRequest->budget_currency : __('messages.custom_requests.not_specified') }}</dd></div>
                    </dl>
                    <p class="text-sm admin-muted">{{ $travelRequest->budget_flexible ? __('messages.custom_requests.flexible_budget') : __('messages.custom_requests.fixed_budget') }}</p>
                </div>
                <div class="grid content-start gap-2">
                    <h3 class="text-sm font-bold admin-heading">{{ __('messages.custom_requests.special_requirements') }}</h3>
                    <p class="whitespace-pre-line text-sm leading-6 admin-muted">{{ $travelRequest->special_requirements ?: __('messages.custom_requests.not_specified') }}</p>
                </div>
            </section>
            <section class="grid gap-2 border-t pt-5 admin-border">
                <h3 class="text-sm font-bold admin-heading">{{ __('messages.custom_requests.sharing') }}</h3>
                <p class="text-sm admin-muted">{{ $travelRequest->sharing_mode === 'all' ? __('messages.custom_requests.share_all') : __('messages.custom_requests.select_agencies') }} · {{ $travelRequest->recipients->count() }} {{ __('messages.custom_requests.agencies_contacted') }}</p>
                <ul class="grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ($travelRequest->recipients as $recipient)
                        <li class="border p-3 admin-border">
                            <span class="font-semibold admin-heading">{{ $recipient->agency->company_name }}</span>
                            <span class="block text-xs admin-muted">{{ __('messages.custom_requests.recipient_statuses.'.$recipient->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </article>
    @if ($moderationActions !== [])
        <section class="admin-empty-state grid gap-3 border p-5">
            <h2 class="font-bold admin-heading">{{ __('messages.custom_requests.admin_actions') }}</h2>
            <form method="POST" action="{{ route('admin.custom-requests.moderate.submit', ['travelRequest' => $travelRequest->id, 'queue' => $queue, 'lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="grid gap-1 text-xs font-semibold admin-muted">{{ __('messages.custom_requests.moderation_action') }}
                    <select name="action" required class="admin-input min-h-10 w-full border px-3 text-sm">
                        @foreach ($moderationActions as $action)
                            <option value="{{ $action }}" @selected(old('action', $moderationActions[0]) === $action)>{{ __('messages.custom_requests.actions.'.$action) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-semibold admin-muted">{{ __('messages.custom_requests.reason_optional') }}
                    <textarea name="reason" minlength="5" maxlength="2000" rows="2" class="admin-input w-full border p-2">{{ old('reason') }}</textarea>
                </label>
                <button class="admin-nav-link min-h-10 w-fit border px-3 py-2 text-sm font-semibold sm:col-span-2">{{ __('messages.custom_requests.save_moderation_action') }}</button>
            </form>
        </section>
    @endif
    <section class="admin-empty-state grid gap-4 border p-5"><h2 class="text-lg font-bold admin-heading">{{ __('messages.custom_requests.recipients') }}</h2><div class="grid gap-3">@forelse ($travelRequest->recipients as $recipient)<article class="grid gap-2 border-b pb-3 admin-border sm:grid-cols-4"><p class="font-semibold admin-heading">{{ $recipient->agency->company_name }}</p><p class="text-sm admin-muted">{{ __('messages.custom_requests.recipient_statuses.'.$recipient->status) }}</p><p class="text-sm admin-muted">{{ __('messages.custom_requests.first_viewed') }}: {{ $recipient->first_viewed_at?->format('M j, Y H:i') ?? '—' }}<br>{{ __('messages.custom_requests.explicit_read') }}: {{ $recipient->read_at?->format('M j, Y H:i') ?? '—' }}</p><p class="text-sm admin-muted">{{ __('messages.custom_requests.available_at') }}: {{ $recipient->available_at?->format('M j, Y H:i') }}</p></article>@empty<p class="text-sm admin-muted">{{ __('messages.custom_requests.no_recipients') }}</p>@endforelse</div></section>
    <section class="admin-empty-state grid gap-4 border p-5">
        <h2 class="text-lg font-bold admin-heading">{{ __('messages.custom_requests.agency_proposals') }}</h2>
        @forelse ($travelRequest->proposals as $proposal)
            <article class="grid gap-3 border-b pb-4 admin-border">
                <header class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="font-bold admin-heading">{{ $proposal->agency->company_name }} · {{ $proposal->reference }}</h3>
                        <p class="text-xs admin-muted">{{ $proposal->owner?->name }} · {{ $proposal->submitted_at?->format('M j, Y H:i') }}</p>
                    </div>
                    <p class="font-bold admin-heading">{{ number_format((float) $proposal->price, 2) }} {{ $proposal->currency }} · {{ __('messages.custom_requests.statuses.'.$proposal->status) }}</p>
                </header>
                <p class="whitespace-pre-line text-sm admin-heading">{{ $proposal->message }}</p>
                <p class="text-sm admin-muted">{{ __('messages.custom_requests.services_included') }}: {{ collect($proposal->services_included)->join(', ') }}</p>
                <p class="text-sm admin-muted">{{ __('messages.custom_requests.services_excluded') }}: {{ collect($proposal->services_excluded)->join(', ') }}</p>
                <details class="grid gap-3 text-sm">
                    <summary class="cursor-pointer font-semibold">{{ __('messages.custom_requests.revision_history') }}</summary>
                    @forelse ($proposal->revisions as $revision)
                        <article class="grid gap-3 border p-3 admin-border">
                            <p class="text-xs admin-muted">
                                {{ $revision->created_at?->format('M j, Y H:i') }}
                                · {{ $proposal->agency->company_name }}
                                · {{ __('messages.custom_requests.revision_actor') }}: {{ $revision->actor?->name ?? __('messages.admin_bookings.unavailable') }}
                            </p>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                @if (data_get($revision->snapshot, 'price') !== null)
                                    <div class="grid gap-1">
                                        <dt class="text-xs admin-muted">{{ __('messages.custom_requests.proposal_price') }}</dt>
                                        <dd class="font-semibold admin-heading">{{ number_format((float) data_get($revision->snapshot, 'price'), 2) }} {{ data_get($revision->snapshot, 'currency', $proposal->currency) }}</dd>
                                    </div>
                                @endif
                                @foreach ([
                                    'itinerary' => 'itinerary',
                                    'estimated_timeline' => 'estimated_timeline',
                                    'message' => 'revision_message',
                                    'terms' => 'revision_terms',
                                    'valid_until' => 'quote_valid_until',
                                ] as $field => $label)
                                    @php($value = data_get($revision->snapshot, $field))
                                    @if ($value !== null && $value !== '')
                                        <div class="grid gap-1">
                                            <dt class="text-xs admin-muted">{{ __('messages.custom_requests.'.$label) }}</dt>
                                            <dd class="whitespace-pre-line break-words admin-heading">{{ $value }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                            @foreach ([
                                'price_breakdown' => 'price_breakdown',
                                'services_included' => 'services_included',
                                'services_excluded' => 'services_excluded',
                            ] as $field => $label)
                                @php($items = collect(is_array(data_get($revision->snapshot, $field)) ? data_get($revision->snapshot, $field) : []))
                                @if ($items->isNotEmpty())
                                    <div class="grid gap-1">
                                        <h4 class="text-xs font-semibold admin-muted">{{ __('messages.custom_requests.'.$label) }}</h4>
                                        <ul class="grid list-disc gap-1 pl-5 text-sm admin-heading">
                                            @foreach ($items as $item)
                                                <li>{{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </article>
                    @empty
                        <p class="text-sm admin-muted">{{ __('messages.custom_requests.no_revisions') }}</p>
                    @endforelse
                </details>
            </article>
        @empty
            <p class="text-sm admin-muted">{{ __('messages.custom_requests.no_proposals') }}</p>
        @endforelse
    </section>
    @if ($travelRequest->booking)<section class="admin-empty-state grid gap-3 border p-5"><h2 class="font-bold admin-heading">{{ __('messages.custom_requests.booking_outcome') }}</h2><p class="text-sm admin-heading">{{ $travelRequest->booking->booking_reference }} · {{ __('messages.custom_requests.statuses.'.$travelRequest->booking->status->value) }} · {{ __('messages.custom_requests.payment_status') }}: {{ __('messages.admin_bookings.payment_statuses.'.$travelRequest->booking->payment_status->value) }}</p><p class="text-sm admin-muted">{{ number_format((float) $travelRequest->booking->total_amount, 2) }} {{ $travelRequest->booking->currency }} · {{ __('messages.custom_requests.agreed_quote_snapshot') }}</p><a class="w-fit font-semibold underline" href="{{ route('admin.bookings.show', ['booking' => $travelRequest->booking->id, 'lang' => app()->getLocale()]) }}">{{ __('messages.custom_requests.view_booking') }}</a></section>@endif
    <section class="admin-empty-state grid gap-4 border p-5"><h2 class="text-lg font-bold admin-heading">{{ __('messages.custom_requests.history') }}</h2>@foreach ($travelRequest->events as $event)<p class="text-sm admin-muted">{{ $event->created_at?->format('M j, Y H:i') }} · {{ $event->actor?->name ?? __('messages.custom_requests.system') }} · {{ __('messages.custom_requests.events.'.$event->event_type) }}</p>@endforeach</section>
    <section class="admin-empty-state grid gap-3 border p-5"><h2 class="font-bold admin-heading">{{ __('messages.custom_requests.private_notes') }}</h2>@foreach ($travelRequest->adminNotes as $note)<p class="border-b pb-2 text-sm admin-muted">{{ $note->created_at?->format('M j, Y H:i') }} · {{ $note->admin?->name }} — {{ $note->content }}</p>@endforeach<form method="POST" action="{{ route('admin.custom-requests.notes.store', ['travelRequest' => $travelRequest->id, 'lang' => app()->getLocale()]) }}" class="grid gap-2">@csrf<textarea required minlength="3" maxlength="5000" name="content" rows="3" class="admin-input border p-3" aria-label="{{ __('messages.custom_requests.private_notes') }}"></textarea><button class="admin-nav-link w-fit border px-4 py-2 text-sm font-semibold">{{ __('messages.custom_requests.save_note') }}</button></form></section>
</section>
