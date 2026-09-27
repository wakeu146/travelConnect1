<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.payments')" :title="__('messages.booking_payments')" :description="__('messages.booking_payments_intro')" />

    <x-agency.panel class="mb-6">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div class="min-w-0">
                <h2 class="text-base font-bold">{{ __('messages.booking_amounts_by_currency') }}</h2>
                <p class="agency-text-subtle mt-2 max-w-2xl text-sm leading-6">{{ __('messages.payment_status_summary_note') }}</p>
            </div>
            <x-agency.metric :label="__('messages.paid_bookings')" :value="$paidBookingCount" class="min-w-40 p-4" />
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse text-left text-sm">
                <caption class="sr-only">{{ __('messages.booking_amounts_by_currency') }}</caption>
                <thead class="agency-panel-muted agency-text-subtle text-xs font-semibold uppercase tracking-[.1em]">
                    <tr>
                        <th scope="col" class="w-28 px-4 py-3">{{ __('messages.currency') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('messages.booked_value') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('messages.paid_booking_value') }}</th>
                        <th scope="col" class="px-4 py-3 text-right">{{ __('messages.unpaid_booking_value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y agency-divider">
                    @forelse ($currencyTotals as $currencyTotal)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $currencyTotal->currency }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $currencyTotal->booked_value, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $currencyTotal->paid_value, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $currencyTotal->unpaid_value, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="agency-text-subtle px-4 py-5">{{ __('messages.no_bookings') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-agency.panel>

    <x-agency.panel class="mb-6">
        <form method="GET" action="{{ route('agency.payments.index', ['lang' => app()->getLocale()]) }}" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-12 xl:items-end">
            <label class="min-w-0 text-xs font-bold uppercase tracking-[.16em] agency-text-subtle sm:col-span-2 xl:col-span-5" for="payment-search">{{ __('messages.search') }}
                <input id="payment-search" name="search" value="{{ $search }}" placeholder="{{ __('messages.reference_traveler_package') }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
            </label>
            <label class="min-w-0 text-xs font-bold uppercase tracking-[.16em] agency-text-subtle sm:col-span-1 xl:col-span-4" for="payment-status">{{ __('messages.payment_status') }}
                <select id="payment-status" name="payment_status" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
                    <option value="">{{ __('messages.all_statuses') }}</option>
                    @foreach (['paid', 'unpaid', 'partial', 'refunded'] as $paymentState)
                        <option value="{{ $paymentState }}" @selected($paymentStatus === $paymentState)>{{ __('messages.payment_status_'.$paymentState) }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold sm:col-span-1 xl:col-span-3">{{ __('messages.apply_filters') }}</button>
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