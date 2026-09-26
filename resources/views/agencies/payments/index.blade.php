<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.payments')" :title="__('messages.booking_payments')" :description="__('messages.booking_payments_intro')" />

    <x-agency.panel class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-base font-bold">{{ __('messages.booking_amounts_by_currency') }}</h2><p class="agency-text-subtle mt-1 text-xs">{{ __('messages.payment_status_summary_note') }}</p></div><x-agency.metric :label="__('messages.paid_bookings')" :value="$paidBookingCount" class="min-w-36 p-3" /></div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm"><thead class="agency-text-subtle text-xs uppercase tracking-[.12em]"><tr><th class="pb-3">{{ __('messages.currency') }}</th><th class="pb-3">{{ __('messages.booked_value') }}</th><th class="pb-3">{{ __('messages.paid_booking_value') }}</th><th class="pb-3">{{ __('messages.unpaid_booking_value') }}</th></tr></thead><tbody class="divide-y agency-divider">
                @forelse ($currencyTotals as $currencyTotal)
                    <tr><td class="py-3 font-semibold">{{ $currencyTotal->currency }}</td><td class="py-3 tabular-nums">{{ number_format((float) $currencyTotal->booked_value, 2) }}</td><td class="py-3 tabular-nums">{{ number_format((float) $currencyTotal->paid_value, 2) }}</td><td class="py-3 tabular-nums">{{ number_format((float) $currencyTotal->unpaid_value, 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="agency-text-subtle py-3">{{ __('messages.no_payment_records') }}</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </x-agency.panel>

    <x-agency.panel class="mb-6 p-4">
        <form method="GET" action="{{ route('agency.payments.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_220px_auto] sm:items-end">
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="payment-search">{{ __('messages.search') }}
                <input id="payment-search" name="search" value="{{ $search }}" placeholder="{{ __('messages.reference_traveler_package') }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
            </label>
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="payment-status">{{ __('messages.payment_status') }}
                <select id="payment-status" name="payment_status" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
                    <option value="">{{ __('messages.all_statuses') }}</option>
                    @foreach (['paid', 'unpaid', 'partial', 'refunded'] as $paymentState)
                        <option value="{{ $paymentState }}" @selected($paymentStatus === $paymentState)>{{ __('messages.payment_status_'.$paymentState) }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">{{ __('messages.apply_filters') }}</button>
        </form>
    </x-agency.panel>

    <x-agency.panel class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-sm">
                <thead class="agency-panel-muted text-xs uppercase tracking-[.14em] agency-text-subtle"><tr>
                    <th scope="col" class="px-5 py-4">{{ __('messages.reference') }}</th>
                    <th scope="col" class="px-5 py-4">{{ __('messages.traveler') }}</th>
                    <th scope="col" class="px-5 py-4">{{ __('messages.package') }}</th>
                    <th scope="col" class="px-5 py-4">{{ __('messages.total') }}</th>
                    <th scope="col" class="px-5 py-4">{{ __('messages.payment_status') }}</th>
                    <th scope="col" class="px-5 py-4">{{ __('messages.date') }}</th>
                </tr></thead>
                <tbody class="divide-y agency-divider">
                    @forelse ($bookings as $booking)
                        @php $paymentState = $booking->payment_status->value ?? $booking->payment_status; @endphp
                        <tr>
                            <td class="px-5 py-4"><a class="agency-accent-text font-semibold" href="{{ route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}">{{ $booking->booking_reference }}</a></td>
                            <td class="px-5 py-4">{{ $booking->traveler?->name ?? __('messages.guest') }}</td>
                            <td class="px-5 py-4">{{ $booking->package?->title ?? __('messages.travel_package') }}</td>
                            <td class="px-5 py-4 tabular-nums">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</td>
                            <td class="px-5 py-4"><x-agency.status-badge :label="__('messages.payment_status_'.$paymentState)" :tone="$paymentState === 'paid' ? 'success' : ($paymentState === 'refunded' ? 'info' : 'warning')" /></td>
                            <td class="px-5 py-4 agency-text-subtle">{{ $booking->created_at->translatedFormat('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-0"><x-agency.empty-state :title="__('messages.no_payment_records')" class="m-5" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($bookings->hasPages())<div class="border-t agency-divider px-5 py-4">{{ $bookings->links() }}</div>@endif
    </x-agency.panel>
</x-agency-shell>