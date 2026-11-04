<x-agency-shell>
    <div class="mx-auto max-w-4xl">
            @php
                $bookingStatus = $booking->status->value;
                $paymentStatus = $booking->payment_status->value;
                $isPendingExpired = $bookingStatus === 'pending' && $booking->expires_at && $booking->expires_at->isPast();
            @endphp
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.booking') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.booking_details') }}</h1>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('agency.bookings.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.all_bookings') }}</a>
                </div>
            </div>

            @if (session('status'))
                <div class="agency-panel mb-6 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="agency-panel rounded-2xl border p-5 shadow-sm sm:p-6">
                <section aria-labelledby="booking-overview-title" class="border-b border-[#e7eceb] pb-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.reference') }}</p>
                            <h2 id="booking-overview-title" class="mt-2 break-all text-2xl font-bold sm:text-3xl">{{ $booking->booking_reference }}</h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-agency.status-badge :label="__('messages.booking_status_'.$bookingStatus)" :tone="$bookingStatus === 'confirmed' || $bookingStatus === 'completed' ? 'success' : (in_array($bookingStatus, ['rejected', 'cancelled', 'expired'], true) ? 'danger' : 'warning')" />
                            <x-agency.status-badge :label="__('messages.payment_status_'.$paymentStatus)" :tone="$paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'unpaid' ? 'warning' : 'neutral')" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-[#607985]">
                        <p>{{ __('messages.created') }} <time datetime="{{ $booking->created_at->toIso8601String() }}">{{ $booking->created_at->translatedFormat('d M Y, H:i') }}</time></p>
                        @if (in_array($bookingStatus, ['pending', 'expired'], true) && $booking->expires_at)
                            <p>{{ __('messages.booking_expiry_deadline') }} <time datetime="{{ $booking->expires_at->toIso8601String() }}">{{ $booking->expires_at->translatedFormat('d M Y, H:i') }}</time></p>
                        @endif
                    </div>
                    @if ($isPendingExpired)
                        <p class="mt-4 rounded-lg border border-[#f3d3a2] bg-[#fff8e8] px-4 py-3 text-sm font-medium text-[#8c651d]">{{ __('messages.booking_expired_notice') }}</p>
                    @endif
                </section>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    <section class="agency-panel-muted rounded-xl border p-5" aria-labelledby="booking-traveler-title">
                        <h3 id="booking-traveler-title" class="text-xs font-bold uppercase tracking-[.16em] text-[#607985]">{{ __('messages.traveler_details') }}</h3>
                        <div class="mt-4 flex items-start gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#173042] text-lg font-bold text-white">
                                @if ($travelerPhotoUrl)
                                    <img src="{{ $travelerPhotoUrl }}" alt="{{ $booking->traveler->name }}" class="h-full w-full object-cover">
                                @else
                                    <span data-traveler-avatar-fallback aria-hidden="true">{{ str($booking->traveler?->name ?? __('messages.guest'))->substr(0, 1)->upper() }}</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="break-words text-lg font-bold">{{ $booking->traveler?->name ?? __('messages.guest') }}</p>
                                @if ($booking->traveler?->email)<a href="mailto:{{ $booking->traveler->email }}" class="mt-1 block break-all text-sm text-[#607985] hover:text-[#e76f51]">{{ $booking->traveler->email }}</a>@endif
                                @if ($booking->traveler?->phone)<a href="tel:{{ $booking->traveler->phone }}" class="mt-1 block text-sm text-[#607985] hover:text-[#e76f51]">{{ $booking->traveler->phone }}</a>@endif
                            </div>
                        </div>
                        <p class="mt-4 border-t border-[#dfe7e5] pt-3 text-sm font-semibold">{{ __('messages.travelers_count', ['count' => $booking->traveler_count]) }}</p>
                    </section>

                    <section class="agency-panel-muted rounded-xl border p-5" aria-labelledby="booking-trip-title">
                        <h3 id="booking-trip-title" class="text-xs font-bold uppercase tracking-[.16em] text-[#607985]">{{ __('messages.trip_details') }}</h3>
                        <p class="mt-3 text-lg font-bold">{{ $booking->package?->title ?? $booking->customRequest?->title ?? __('messages.travel_package') }}</p>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs text-[#607985]">{{ __('messages.destination') }}</dt><dd class="mt-1 font-semibold">{{ $booking->customRequest?->destination ?? $booking->package?->destination ?? __('messages.not_available') }}</dd></div>
                            <div><dt class="text-xs text-[#607985]">{{ __('messages.departure') }}</dt><dd class="mt-1 font-semibold">{{ $booking->customRequest?->travel_date?->translatedFormat('d M Y') ?? $booking->availability?->departure_date?->translatedFormat('d M Y') ?? __('messages.not_available') }}</dd></div>
                            <div><dt class="text-xs text-[#607985]">{{ __('messages.package_duration') }}</dt><dd class="mt-1 font-semibold">{{ $booking->package?->duration_days ? __('messages.duration_days_label', ['count' => $booking->package->duration_days]) : __('messages.not_available') }}</dd></div>
                            <div><dt class="text-xs text-[#607985]">{{ __('messages.availability') }}</dt><dd class="mt-1 font-semibold">{{ $booking->availability?->status ? __('messages.'.$booking->availability->status->value) : __('messages.not_available') }}</dd></div>
                        </dl>
                    </section>
                </div>

                <section class="mt-6" aria-labelledby="booking-financial-title">
                    <h3 id="booking-financial-title" class="text-xs font-bold uppercase tracking-[.16em] text-[#607985]">{{ __('messages.financial_snapshot') }}</h3>
                    <dl class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="agency-panel-muted rounded-xl p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.unit_price') }}</dt><dd class="mt-2 text-lg font-bold">{{ $booking->currency }} {{ number_format((float) $booking->unit_price, 2) }}</dd></div>
                        <div class="agency-panel-muted rounded-xl p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.booking_travelers_label') }}</dt><dd class="mt-2 text-lg font-bold">{{ $booking->traveler_count }}</dd></div>
                        <div class="agency-panel-muted rounded-xl p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.total') }}</dt><dd class="mt-2 text-lg font-bold">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</dd></div>
                        <div class="agency-panel-muted rounded-xl p-4"><dt class="text-xs font-semibold text-[#607985]">{{ __('messages.payment_status') }}</dt><dd class="mt-2 text-lg font-bold">{{ __('messages.payment_status_'.$paymentStatus) }}</dd></div>
                    </dl>
                </section>

                @if ($capacitySnapshot)
                    <section class="mt-6 border-t border-[#e7eceb] pt-5" aria-labelledby="booking-capacity-title">
                        <h3 id="booking-capacity-title" class="text-xs font-bold uppercase tracking-[.16em] text-[#607985]">{{ __('messages.departure_capacity_context') }}</h3>
                        <p class="agency-text-subtle mt-2 text-sm">{{ __('messages.departure_capacity_reservation_hint') }}</p>
                        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs text-[#607985]">{{ __('messages.capacity') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['capacity'] }}</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs text-[#607985]">{{ __('messages.confirmed_travelers') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['confirmed_travelers'] }}</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs text-[#607985]">{{ __('messages.pending_travelers') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['pending_travelers'] }}</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs text-[#607985]">{{ __('messages.remaining_capacity') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['remaining_capacity'] }}</dd></div>
                            <div class="agency-panel-muted rounded-xl p-3"><dt class="text-xs text-[#607985]">{{ __('messages.occupancy') }}</dt><dd class="mt-1 text-lg font-bold">{{ $capacitySnapshot['occupancy_percent'] }}%</dd></div>
                        </dl>
                        @if ($capacitySnapshot['sold_out'] || $capacitySnapshot['near_capacity'])
                            <p class="mt-3 inline-flex rounded-lg border px-3 py-2 text-sm font-semibold {{ $capacitySnapshot['sold_out'] ? 'agency-state-danger' : 'agency-state-warning' }}">{{ $capacitySnapshot['sold_out'] ? __('messages.departure_sold_out') : __('messages.departure_near_capacity') }}</p>
                        @endif
                    </section>
                @endif

                @if ($booking->notes)
                    <div class="agency-panel-muted mt-8 rounded-xl p-4">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.notes') }}</p>
                        <p class="mt-2 text-sm text-[#173042]">{{ $booking->notes }}</p>
                    </div>
                @endif

                @if ($booking->cancellation_reason)
                    <div class="agency-state-warning mt-5 rounded-xl border p-4">
                        <p class="text-xs font-bold uppercase tracking-[.16em]">{{ __('messages.booking_cancellation_reason') }}</p>
                        <p class="mt-2 text-sm">{{ $booking->cancellation_reason }}</p>
                    </div>
                @endif

                @php
                    $timeline = [['label' => __('messages.timeline_booking_created'), 'at' => $booking->created_at]];
                    foreach ([
                        'confirmed_at' => 'timeline_confirmed',
                        'rejected_at' => 'timeline_rejected',
                        'cancelled_at' => 'timeline_cancelled',
                        'completed_at' => 'timeline_completed',
                    ] as $timestamp => $label) {
                        if ($booking->{$timestamp}) {
                            $timeline[] = ['label' => __('messages.'.$label), 'at' => $booking->{$timestamp}];
                        }
                    }
                    $timeline = collect($timeline)->sortBy('at')->values();
                @endphp
                <section class="mt-8 border-t agency-divider pt-6" aria-labelledby="booking-timeline-title">
                    <h3 id="booking-timeline-title" class="text-base font-bold">{{ __('messages.booking_timeline') }}</h3>
                    <ol class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($timeline as $event)
                            <li class="agency-panel-muted flex items-start gap-3 rounded-xl border p-3">
                                <span class="agency-icon agency-icon-success mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs" aria-hidden="true">✓</span>
                                <span class="min-w-0"><span class="block text-sm font-semibold">{{ $event['label'] }}</span><time class="agency-text-subtle mt-1 block text-xs" datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->translatedFormat('d M Y, H:i') }}</time></span>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="mt-8 border-t agency-divider pt-6" aria-labelledby="traveler-booking-history-title">
                    <h3 id="traveler-booking-history-title" class="text-base font-bold">{{ __('messages.related_traveler_bookings') }}</h3>
                    <div class="mt-3 divide-y divide-[#e7eceb]">
                        @forelse ($otherTravelerBookings as $otherBooking)
                            <a href="{{ route('agency.bookings.show', ['booking' => $otherBooking->id, 'lang' => app()->getLocale()]) }}" class="flex flex-col justify-between gap-2 py-3 text-sm transition hover:text-[#e76f51] sm:flex-row sm:items-center">
                                <span><span class="font-bold">{{ $otherBooking->booking_reference }}</span> · {{ $otherBooking->package?->title ?? __('messages.travel_package') }}</span>
                                <span class="agency-text-subtle">{{ $otherBooking->availability?->departure_date?->translatedFormat('d M Y') ?? __('messages.not_available') }} · {{ __('messages.booking_status_'.$otherBooking->status->value) }}</span>
                            </a>
                        @empty
                            <p class="agency-text-subtle py-3 text-sm">{{ __('messages.no_other_traveler_bookings') }}</p>
                        @endforelse
                    </div>
                </section>

                @php
                    $statusValue = $booking->status->value ?? $booking->status;
                @endphp
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($statusValue === 'pending' && ! $isPendingExpired)
                        <form method="POST" action="{{ route('agency.bookings.confirm', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#2f9e7a] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#1f7d60]">{{ __('messages.confirm_booking') }}</button>
                        </form>
                        <form method="POST" action="{{ route('agency.bookings.reject', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" onsubmit='return confirm(@json(__('messages.reject_booking_confirm')) )'>
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.reject_booking') }}</button>
                        </form>
                    @endif

                    @if ($statusValue === 'confirmed' || ($statusValue === 'pending' && ! $isPendingExpired))
                        <form method="POST" action="{{ route('agency.bookings.cancel', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" onsubmit='return confirm(@json(__('messages.cancel_booking_confirm')) )'>
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.cancel_booking') }}</button>
                        </form>
                    @endif

                    @if ($statusValue === 'confirmed')
                        <form method="POST" action="{{ route('agency.bookings.complete', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#102936]">{{ __('messages.mark_complete') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-agency-shell>
