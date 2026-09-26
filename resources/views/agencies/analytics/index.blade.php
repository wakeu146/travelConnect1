<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.analytics')" :title="__('messages.business_analytics')" :description="__('messages.analytics_intro')" />

    <x-agency.panel class="mb-6 p-4">
        <form method="GET" action="{{ route('agency.analytics.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="analytics-from">{{ __('messages.date_from') }}
                <input id="analytics-from" name="from" type="date" value="{{ $from }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
            </label>
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="analytics-to">{{ __('messages.date_to') }}
                <input id="analytics-to" name="to" type="date" value="{{ $to }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">{{ __('messages.apply_filters') }}</button>
        </form>
    </x-agency.panel>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-agency.metric :label="__('messages.bookings_in_period')" :value="$bookingCount" />
        <x-agency.metric :label="__('messages.travelers_in_period')" :value="$travelerCount" />
        <x-agency.panel class="sm:col-span-2">
            <h2 class="text-sm font-bold">{{ __('messages.booking_amounts_by_currency') }}</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @forelse ($currencyTotals as $currencyTotal)
                    <div class="agency-panel-muted rounded-xl border p-3"><p class="agency-text-primary text-sm font-semibold">{{ $currencyTotal->currency }}</p><p class="agency-text-subtle mt-1 text-xs">{{ __('messages.booked_value') }} · {{ number_format((float) $currencyTotal->booked_value, 2) }}</p><p class="agency-text-success mt-1 text-xs">{{ __('messages.paid_booking_value') }} · {{ number_format((float) $currencyTotal->paid_value, 2) }}</p></div>
                @empty
                    <p class="agency-text-subtle text-sm">{{ __('messages.no_analytics_data') }}</p>
                @endforelse
            </div>
        </x-agency.panel>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(260px,.6fr)]">
        <x-agency.panel>
            <div class="flex items-end justify-between gap-4">
                <div><h2 class="text-base font-bold">{{ __('messages.booking_activity') }}</h2><p class="agency-text-subtle mt-1 text-xs">{{ $from }} – {{ $to }}</p></div>
                <span class="agency-pill agency-pill-neutral rounded-full px-2.5 py-1 text-xs font-bold">{{ $daily->sum('booking_count') }}</span>
            </div>
            @php $maxDailyBookings = max(1, (int) $daily->max('booking_count')); @endphp
            @if ($daily->isNotEmpty())
                <div class="mt-6 flex h-48 items-end gap-2 overflow-x-auto border-b agency-divider pb-3" role="img" aria-label="{{ __('messages.booking_activity_chart') }}">
                    @foreach ($daily as $day)
                        <div class="flex h-full min-w-8 flex-1 flex-col items-center justify-end gap-2" title="{{ $day->activity_date }}: {{ $day->booking_count }}">
                            <div class="agency-progress-bar w-full max-w-8 rounded-t-md" style="height: {{ max(4, round(((int) $day->booking_count / $maxDailyBookings) * 100)) }}%"></div>
                            <span class="agency-text-subtle text-[10px]">{{ substr($day->activity_date, 5) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-2">
                    @foreach ($daily->reverse()->take(5) as $day)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="agency-text-subtle">{{ \Carbon\Carbon::parse($day->activity_date)->translatedFormat('d M Y') }}</span>
                            <span class="font-semibold tabular-nums">{{ $day->booking_count }} · {{ __('messages.travelers_count', ['count' => $day->traveler_count]) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <x-agency.empty-state :title="__('messages.no_analytics_data')" class="mt-5" />
            @endif
        </x-agency.panel>

        <x-agency.panel>
            <h2 class="text-base font-bold">{{ __('messages.booking_status_breakdown') }}</h2>
            <div class="mt-5 space-y-3">
                @foreach (['pending', 'confirmed', 'rejected', 'cancelled', 'completed', 'expired'] as $bookingState)
                    <div class="flex items-center justify-between gap-3 border-b agency-divider pb-3 last:border-0">
                        <span class="agency-text-subtle text-sm">{{ __('messages.booking_status_'.$bookingState) }}</span>
                        <span class="font-semibold tabular-nums">{{ (int) ($statusCounts[$bookingState] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="agency-panel-muted mt-5 rounded-xl p-4">
                <p class="agency-text-subtle text-xs">{{ __('messages.active_package_inventory') }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $packageCount }}</p>
            </div>
        </x-agency.panel>
    </div>
</x-agency-shell>