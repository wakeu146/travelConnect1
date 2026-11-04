<x-agency-shell>
    <div class="mx-auto max-w-6xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.bookings') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.booking_management') }}</h1>
                </div>
                <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.back_to_dashboard') }}</a>
            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <x-agency.metric :label="__('messages.total_bookings')" :value="$totalBookingCount" :note="__('messages.all_statuses_included')" />
                <x-agency.metric :label="__('messages.pending_bookings')" :value="(int) ($statusCounts['pending'] ?? 0)" tone="warning" />
                <x-agency.metric :label="__('messages.confirmed_bookings')" :value="(int) ($statusCounts['confirmed'] ?? 0)" tone="success" />
                <x-agency.metric :label="__('messages.upcoming_confirmed_bookings')" :value="$upcomingConfirmedCount" tone="success" :note="__('messages.upcoming_confirmed_bookings_hint')" />
                <x-agency.metric :label="__('messages.upcoming_travelers')" :value="$upcomingTravelerCount" :note="__('messages.upcoming_travelers_hint')" />
                <x-agency.metric :label="__('messages.cancelled_rejected_bookings')" :value="$cancelledRejectedCount" tone="warning" />
            </div>

            @php
                $hasBookingFilters = $search !== '' || $status !== '' || $paymentStatus !== '' || $packageId !== '' || $availabilityId !== '' || $travelerId !== '' || $date !== '' || $createdFrom !== '' || $createdTo !== '' || $departureFrom !== '' || $departureTo !== '' || $upcoming;
            @endphp
            <div class="agency-panel mb-6 rounded-xl border p-4 shadow-sm">
                <form method="GET" action="{{ route('agency.bookings.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                    <label class="min-w-0 sm:col-span-2 xl:col-span-2"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.search') }}</span><input name="search" value="{{ $search }}" placeholder="{{ __('messages.booking_search_hint') }}" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.status') }}</span><select name="status" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"><option value="">{{ __('messages.all') }}</option>@foreach (\App\Enums\BookingStatus::cases() as $bookingStatusOption)<option value="{{ $bookingStatusOption->value }}" @selected($status === $bookingStatusOption->value)>{{ __('messages.booking_status_'.$bookingStatusOption->value) }}</option>@endforeach</select></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.payment_status') }}</span><select name="payment_status" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"><option value="">{{ __('messages.all') }}</option>@foreach (\App\Enums\PaymentStatus::cases() as $paymentStatusOption)<option value="{{ $paymentStatusOption->value }}" @selected($paymentStatus === $paymentStatusOption->value)>{{ __('messages.payment_status_'.$paymentStatusOption->value) }}</option>@endforeach</select></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.package') }}</span><select name="package_id" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"><option value="">{{ __('messages.all_packages') }}</option>@foreach ($packages as $package)<option value="{{ $package->id }}" @selected((string) $packageId === (string) $package->id)>{{ $package->title }}</option>@endforeach</select></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.booking_date') }}</span><input type="date" name="date" value="{{ $date }}" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.created_from') }}</span><input type="date" name="created_from" value="{{ $createdFrom }}" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.created_to') }}</span><input type="date" name="created_to" value="{{ $createdTo }}" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.departure_from') }}</span><input type="date" name="departure_from" value="{{ $departureFrom }}" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.departure_to') }}</span><input type="date" name="departure_to" value="{{ $departureTo }}" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]"></label>
                    <label class="min-w-0"><span class="block text-xs font-bold uppercase tracking-[.14em] text-[#607985]">{{ __('messages.sort_by') }}</span><select name="sort" class="mt-1.5 w-full rounded-lg border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2.5 text-sm outline-none focus:border-[#e76f51]">@foreach (['newest', 'oldest', 'amount_high', 'amount_low', 'travelers_high'] as $sortOption)<option value="{{ $sortOption }}" @selected($sort === $sortOption)>{{ __('messages.sort_'.$sortOption) }}</option>@endforeach</select></label>
                    <label class="flex items-center gap-2 self-end pb-2.5 text-sm font-semibold text-[#173042]"><input type="checkbox" name="upcoming" value="1" @checked($upcoming) class="h-4 w-4 accent-[#e76f51]">{{ __('messages.upcoming_only') }}</label>
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4 xl:col-span-6">
                        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#e76f51] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.apply_filters') }}</button>
                        @if ($hasBookingFilters)<a href="{{ route('agency.bookings.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-lg border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51]">{{ __('messages.clear_filters') }}</a>@endif
                    </div>
                </form>
            </div>

            <div class="overflow-hidden border border-[#e7eceb] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1180px] border-collapse text-left text-sm">
                        <thead class="bg-[#f5f8f7] text-xs uppercase tracking-[.12em] text-[#607985]"><tr><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.booking') }}</th><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.traveler') }}</th><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.package') }}</th><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.departure') }}</th><th scope="col" class="px-4 py-3 text-right font-bold">{{ __('messages.travelers') }}</th><th scope="col" class="px-4 py-3 text-right font-bold">{{ __('messages.total') }}</th><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.payment_status') }}</th><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.status') }}</th><th scope="col" class="px-4 py-3 font-bold">{{ __('messages.next_action') }}</th></tr></thead>
                        <tbody class="divide-y divide-[#e7eceb]">
                            @forelse ($bookings as $booking)
                                @php
                                    $bookingStatus = $booking->status->value;
                                    $paymentStatus = $booking->payment_status->value;
                                    $paymentTone = $paymentStatus === 'paid' ? 'success' : ($paymentStatus === 'unpaid' ? 'warning' : ($paymentStatus === 'refunded' ? 'danger' : 'neutral'));
                                @endphp
                                <tr class="align-top transition hover:bg-[#fbfcfb]">
                                    <td class="px-4 py-4"><a href="{{ route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="font-bold text-[#e76f51] hover:underline">{{ $booking->booking_reference }}</a><p class="mt-1 text-xs text-[#607985]">{{ __('messages.created') }} {{ $booking->created_at->translatedFormat('d M Y') }}</p></td>
                                    <td class="max-w-56 px-4 py-4"><p class="font-semibold text-[#173042]">{{ $booking->traveler?->name ?? __('messages.guest') }}</p>@if ($booking->traveler?->email)<a href="mailto:{{ $booking->traveler->email }}" class="mt-1 block break-all text-xs text-[#607985] hover:text-[#e76f51]">{{ $booking->traveler->email }}</a>@endif @if ($booking->traveler?->phone)<a href="tel:{{ $booking->traveler->phone }}" class="mt-1 block text-xs text-[#607985] hover:text-[#e76f51]">{{ $booking->traveler->phone }}</a>@endif</td>
                                    <td class="max-w-52 px-4 py-4"><p class="font-semibold">{{ $booking->package?->title ?? $booking->customRequest?->title ?? __('messages.travel_package') }}</p><p class="mt-1 text-xs text-[#607985]">{{ $booking->customRequest?->destination ?? $booking->package?->destination ?? __('messages.not_available') }}@if ($booking->package?->duration_days) · {{ __('messages.duration_days_label', ['count' => $booking->package->duration_days]) }}@endif</p></td>
                                    <td class="px-4 py-4"><p class="font-semibold">{{ $booking->customRequest?->travel_date?->translatedFormat('d M Y') ?? $booking->availability?->departure_date?->translatedFormat('d M Y') ?? __('messages.not_available') }}</p><p class="mt-1 text-xs text-[#607985]">{{ __('messages.capacity') }}: {{ $booking->availability?->capacity ?? __('messages.not_available') }}</p></td>
                                    <td class="px-4 py-4 text-right font-semibold tabular-nums">{{ $booking->traveler_count }}</td>
                                    <td class="px-4 py-4 text-right"><p class="font-bold tabular-nums">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</p><p class="mt-1 text-xs text-[#607985]">{{ __('messages.unit_price') }} {{ $booking->currency }} {{ number_format((float) $booking->unit_price, 2) }}</p></td>
                                    <td class="px-4 py-4"><x-agency.status-badge :label="__('messages.payment_status_'.$paymentStatus)" :tone="$paymentTone" /></td>
                                    <td class="px-4 py-4"><x-agency.status-badge :label="__('messages.booking_status_'.$bookingStatus)" :tone="$bookingStatus === 'confirmed' || $bookingStatus === 'completed' ? 'success' : (in_array($bookingStatus, ['rejected', 'cancelled', 'expired'], true) ? 'danger' : 'warning')" /></td>
                                    <td class="px-4 py-4"><a href="{{ route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center rounded-lg border border-[#dbe3e5] px-3 py-2 text-xs font-bold text-[#173042] transition hover:border-[#e76f51] hover:text-[#e76f51] focus:outline-none focus:ring-2 focus:ring-[#e76f51]/40">{{ $bookingStatus === 'pending' ? __('messages.review_booking') : __('messages.view_booking') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="px-6 py-14 text-center"><p class="font-semibold text-[#173042]">{{ __('messages.no_bookings_match') }}</p><p class="mt-1 text-sm text-[#607985]">{{ __('messages.adjust_booking_filters') }}</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($bookings->hasPages())
                <div class="mt-6 flex justify-center">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>
    </div>
</x-agency-shell>
