<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.customers')" :title="__('messages.customers')" :description="__('messages.customers_intro')" />

    <x-agency.panel class="mb-6 p-4">
        <form method="GET" action="{{ route('agency.customers.index', ['lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="min-w-0 flex-1 text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="customer-search">
                {{ __('messages.search_customers') }}
                <input id="customer-search" name="search" value="{{ $search }}" placeholder="{{ __('messages.customer_name_or_email') }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">{{ __('messages.search') }}</button>
            @if ($search !== '')
                <a href="{{ route('agency.customers.index', ['lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-xl border px-4 py-3 text-sm font-semibold">{{ __('messages.clear_filters') }}</a>
            @endif
        </form>
    </x-agency.panel>

    <x-agency.panel class="overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="agency-panel-muted text-xs uppercase tracking-[.14em] agency-text-subtle">
                    <tr>
                        <th scope="col" class="px-5 py-4">{{ __('messages.customer') }}</th>
                        <th scope="col" class="px-5 py-4">{{ __('messages.bookings') }}</th>
                        <th scope="col" class="px-5 py-4">{{ __('messages.booking_value') }}</th>
                        <th scope="col" class="px-5 py-4">{{ __('messages.last_booking') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y agency-divider">
                    @forelse ($customers as $customer)
                        @php $lastBookingAt = $customer->last_agency_booking_at ? \Illuminate\Support\Carbon::parse($customer->last_agency_booking_at) : null; @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <p class="agency-text-primary font-semibold">{{ $customer->name }}</p>
                                <p class="agency-text-subtle mt-1 text-xs">{{ $customer->email }}</p>
                            </td>
                            <td class="px-5 py-4 tabular-nums">{{ $customer->agency_bookings_count }}</td>
                            <td class="px-5 py-4"><div class="grid gap-1 tabular-nums">@foreach ($customerValues->get($customer->id, collect()) as $currencyValue)<span>{{ $currencyValue->currency }} {{ number_format((float) $currencyValue->booking_value, 2) }}</span>@endforeach</div></td>
                            <td class="px-5 py-4 agency-text-subtle">{{ $lastBookingAt?->translatedFormat('d M Y') ?? __('messages.not_available') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-0"><x-agency.empty-state :title="__('messages.no_customers_yet')" :description="__('messages.customers_empty_hint')" class="m-5" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="border-t agency-divider px-5 py-4">{{ $customers->links() }}</div>
        @endif
    </x-agency.panel>
</x-agency-shell>