<x-agency-shell>
    <div class="mx-auto max-w-4xl">
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

            <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#e7eceb] pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.reference') }}</p>
                        <h2 class="mt-2 text-3xl font-bold">{{ $booking->booking_reference }}</h2>
                    </div>
                    @php $bookingStatus = $booking->status->value ?? $booking->status; @endphp
                    <x-agency.status-badge :label="__('messages.booking_status_'.$bookingStatus)" :tone="$bookingStatus === 'confirmed' || $bookingStatus === 'completed' ? 'success' : (in_array($bookingStatus, ['rejected', 'cancelled', 'expired'], true) ? 'danger' : 'warning')" />
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.package') }}</p>
                        <p class="mt-2 text-xl font-bold">{{ $booking->package?->title ?? __('messages.travel_package') }}</p>
                        <p class="mt-2 text-sm text-[#607985]">{{ __('messages.departure') }}: {{ $booking->availability?->departure_date?->translatedFormat('F j, Y') ?? __('messages.not_available') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.traveler') }}</p>
                        <p class="mt-2 text-xl font-bold">{{ $booking->traveler?->name ?? __('messages.guest') }}</p>
                        <p class="mt-2 text-sm text-[#607985]">{{ __('messages.travelers_count', ['count' => $booking->traveler_count]) }}</p>
                    </div>
                </div>

                <dl class="mt-8 grid gap-5 md:grid-cols-3">
                    <div class="agency-panel-muted rounded-xl p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.total') }}</dt>
                        <dd class="mt-2 text-xl font-bold">${{ number_format((float) $booking->total_amount, 2) }}</dd>
                    </div>
                    <div class="agency-panel-muted rounded-xl p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.payment') }}</dt>
                        @php $paymentStatus = $booking->payment_status->value ?? $booking->payment_status; @endphp
                        <dd class="mt-2 text-xl font-bold">{{ __('messages.payment_status_'.$paymentStatus) }}</dd>
                    </div>
                    <div class="agency-panel-muted rounded-xl p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.expires') }}</dt>
                        <dd class="mt-2 text-xl font-bold">{{ $booking->expires_at ? $booking->expires_at->format('M d, Y H:i') : __('messages.not_available') }}</dd>
                    </div>
                </dl>

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
                    if ($bookingStatus === 'expired') {
                        $timeline[] = ['label' => __('messages.timeline_expired'), 'at' => $booking->updated_at];
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

                @php
                    $statusValue = $booking->status->value ?? $booking->status;
                @endphp
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($statusValue === 'pending')
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

                    @if (in_array($statusValue, ['pending', 'confirmed'], true))
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
