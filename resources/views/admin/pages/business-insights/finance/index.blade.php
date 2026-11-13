<section class="grid min-w-0 gap-6" data-admin-finance>
    <div class="grid gap-3 border-b pb-5 admin-border">
        <p class="max-w-4xl text-sm leading-6 admin-muted">
            @switch($activePage)
                @case('financial_overview')
                    {{ __('messages.admin_finance.intro_overview') }}
                    @break
                @case('all_transactions')
                    {{ __('messages.admin_finance.intro_transactions') }}
                    @break
                @case('successful_payments')
                    {{ __('messages.admin_finance.intro_successful') }}
                    @break
                @case('pending_failed_payments')
                    {{ __('messages.admin_finance.intro_pending_failed') }}
                    @break
                @case('refunds')
                    {{ __('messages.admin_finance.intro_refunds') }}
                    @break
                @case('payment_disputes')
                    {{ __('messages.admin_finance.intro_disputes') }}
                    @break
                @case('financial_reports')
                    {{ __('messages.admin_finance.intro_reports') }}
                    @break
            @endswitch
        </p>
        <p class="text-xs leading-5 admin-muted">{{ __('messages.admin_finance.offline_payments_note') }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => $activePage, 'lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 border p-4 admin-border sm:flex-row sm:items-end">
        <label class="grid flex-1 gap-1 text-xs font-semibold admin-muted">
            {{ __('messages.admin_finance.date_from') }}
            <input type="date" name="created_from" value="{{ $financeData['from'] }}" class="admin-input min-h-10 w-full min-w-0 px-3 text-sm">
        </label>
        <label class="grid flex-1 gap-1 text-xs font-semibold admin-muted">
            {{ __('messages.admin_finance.date_to') }}
            <input type="date" name="created_to" value="{{ $financeData['to'] }}" class="admin-input min-h-10 w-full min-w-0 px-3 text-sm">
        </label>
        <button type="submit" class="admin-nav-link min-h-10 border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_finance.apply_date_filter') }}</button>
    </form>

    @if (! $financeData['ledgerAvailable'])
        <div class="grid gap-2 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm leading-6 admin-muted">
            <p class="font-semibold admin-heading">{{ __('messages.admin_finance.no_ledger_title') }}</p>
            <p>{{ __('messages.admin_finance.no_ledger_description') }}</p>
        </div>
    @endif

    @if ($activePage === 'financial_overview')
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['key' => 'bookings', 'label' => __('messages.admin_finance.bookings'), 'value' => $financeData['bookingCount']],
                ['key' => 'payment-attempts', 'label' => __('messages.admin_finance.payment_attempts'), 'value' => $financeData['paymentAttemptCount']],
                ['key' => 'confirmed-transactions', 'label' => __('messages.admin_finance.confirmed_transactions'), 'value' => $financeData['confirmedTransactionCount']],
            ] as $metric)
                <article class="admin-empty-state grid gap-2 border p-4" data-finance-metric="{{ $metric['key'] }}" data-finance-value="{{ $metric['value'] }}">
                    <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ $metric['label'] }}</p>
                    <p class="text-2xl font-bold tracking-tight admin-heading">{{ number_format($metric['value']) }}</p>
                </article>
            @endforeach
        </div>

        <section class="admin-empty-state grid min-w-0 gap-5 border p-4 sm:p-6" aria-labelledby="finance-money-flow-title">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="grid gap-1">
                    <h2 id="finance-money-flow-title" class="text-base font-bold admin-heading">{{ __('messages.admin_finance.money_flow_title') }}</h2>
                    <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.admin_finance.money_flow_description') }}</p>
                </div>
                @if ($financeData['moneyTrends'] !== [])
                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs font-semibold admin-muted" aria-label="{{ __('messages.admin_finance.money_flow_title') }}">
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-teal-600"></span>{{ __('messages.admin_finance.charges_series') }}</span>
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-orange-500"></span>{{ __('messages.admin_finance.refunds_series') }}</span>
                    </div>
                @endif
            </header>

            @forelse ($financeData['moneyTrends'] as $trend)
                <article class="grid min-w-0 gap-3 border-t pt-4 admin-border" data-finance-chart-currency="{{ $trend['currency'] }}">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="text-sm font-bold admin-heading">{{ $trend['currency'] }}</h3>
                        <p class="text-xs admin-muted">{{ $trend['range'] }}</p>
                    </div>
                    <div class="w-full min-w-0">
                        <svg viewBox="0 0 800 310" preserveAspectRatio="none" role="img" aria-labelledby="finance-chart-title-{{ $loop->index }} finance-chart-description-{{ $loop->index }}" class="block h-64 w-full overflow-visible sm:h-72 lg:h-80">
                            <title id="finance-chart-title-{{ $loop->index }}">{{ __('messages.admin_finance.money_flow_title') }} · {{ $trend['currency'] }}</title>
                            <desc id="finance-chart-description-{{ $loop->index }}">{{ __('messages.admin_finance.money_flow_description') }}</desc>
                            <defs>
                                <linearGradient id="finance-charge-fill-{{ $loop->index }}" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#0f766e" stop-opacity=".2" />
                                    <stop offset="100%" stop-color="#0f766e" stop-opacity="0" />
                                </linearGradient>
                                <linearGradient id="finance-refund-fill-{{ $loop->index }}" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#f97316" stop-opacity=".14" />
                                    <stop offset="100%" stop-color="#f97316" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <g fill="none" stroke="currentColor" stroke-opacity=".12" stroke-width="1">
                                <path d="M70 30H776" />
                                <path d="M70 140H776" />
                                <path d="M70 250H776" />
                                <path d="M70 30V250" />
                            </g>
                            <g fill="currentColor" font-size="14" class="admin-muted">
                                <text x="60" y="34" text-anchor="end">{{ $trend['maximumLabel'] }}</text>
                                <text x="60" y="144" text-anchor="end">{{ $trend['middleLabel'] }}</text>
                                <text x="60" y="254" text-anchor="end">0</text>
                                <text x="70" y="278" text-anchor="start">{{ $trend['dates'][0] }}</text>
                                <text x="423" y="278" text-anchor="middle">{{ $trend['dates'][(int) floor((count($trend['dates']) - 1) / 2)] }}</text>
                                <text x="776" y="278" text-anchor="end">{{ $trend['dates'][count($trend['dates']) - 1] }}</text>
                            </g>
                            <path d="{{ $trend['chargeAreaPath'] }}" fill="url(#finance-charge-fill-{{ $loop->index }})" />
                            <path d="{{ $trend['refundAreaPath'] }}" fill="url(#finance-refund-fill-{{ $loop->index }})" />
                            <path d="{{ $trend['chargePath'] }}" fill="none" stroke="#0f766e" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" />
                            <path d="{{ $trend['refundPath'] }}" fill="none" stroke="#f97316" stroke-dasharray="7 6" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" />
                            @foreach ($trend['chargePoints'] as $pointIndex => $point)
                                @if ($point['value'] > 0)
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="#0f766e" stroke="white" stroke-width="2">
                                        <title>{{ __('messages.admin_finance.charges_series') }} · {{ $trend['currency'] }} {{ number_format($point['value'], 2) }} · {{ $trend['dates'][$pointIndex] }}</title>
                                    </circle>
                                @endif
                            @endforeach
                            @foreach ($trend['refundPoints'] as $pointIndex => $point)
                                @if ($point['value'] > 0)
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" fill="#f97316" stroke="white" stroke-width="2">
                                        <title>{{ __('messages.admin_finance.refunds_series') }} · {{ $trend['currency'] }} {{ number_format($point['value'], 2) }} · {{ $trend['dates'][$pointIndex] }}</title>
                                    </circle>
                                @endif
                            @endforeach
                        </svg>
                    </div>
                </article>
            @empty
                <div class="grid gap-1 rounded-xl border border-dashed p-5 admin-border">
                    <h3 class="text-sm font-semibold admin-heading">{{ __('messages.admin_finance.no_successful_money_title') }}</h3>
                    <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_finance.no_successful_money_description') }}</p>
                </div>
            @endforelse
        </section>

        <div class="grid gap-3">
            <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_finance.currency_totals') }}</h2>
            @forelse ($financeData['currencySummaries'] as $summary)
                <article class="admin-empty-state grid gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="grid gap-1">
                        <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ __('messages.admin_finance.successful_charge_total') }} · {{ $summary['currency'] }}</p>
                        <p class="text-lg font-bold admin-heading">{{ $summary['currency'] }} {{ number_format((float) $summary['successfulChargeTotal'], 2) }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ __('messages.admin_finance.successful_payments') }}</p>
                        <p class="text-lg font-bold admin-heading">{{ number_format($summary['successfulChargeCount']) }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ __('messages.admin_finance.refunds') }}</p>
                        <p class="text-lg font-bold admin-heading">{{ $summary['currency'] }} {{ number_format((float) $summary['refundTotal'], 2) }}</p>
                    </div>
                    <div class="grid gap-1">
                        <p class="text-xs font-semibold uppercase tracking-[.12em] admin-muted">{{ __('messages.admin_finance.pending_failed_summary') }}</p>
                        <p class="text-sm admin-heading">{{ __('messages.admin_finance.status_pending') }}: {{ number_format($summary['pendingCount']) }} · {{ __('messages.admin_finance.status_failed') }}: {{ number_format($summary['failedCount']) }}</p>
                    </div>
                </article>
            @empty
                <p class="admin-empty-state border p-4 text-sm admin-muted">{{ __('messages.admin_finance.no_transactions_for_period') }}</p>
            @endforelse
        </div>

        <div class="grid gap-3 rounded-xl border border-dashed p-4 admin-border">
            <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_finance.settlements_unavailable_title') }}</h2>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_finance.settlements_unavailable_description') }}</p>
        </div>
    @elseif ($activePage === 'financial_reports')
        <div class="grid gap-3">
            <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_finance.report_title') }}</h2>
            @forelse ($financeData['currencySummaries'] as $summary)
                <article class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-2 lg:grid-cols-4">
                    <p class="font-semibold admin-heading">{{ $summary['currency'] }}</p>
                    <p class="text-sm admin-muted">{{ __('messages.admin_finance.successful_charge_total') }}: {{ $summary['currency'] }} {{ number_format((float) $summary['successfulChargeTotal'], 2) }} ({{ number_format($summary['successfulChargeCount']) }})</p>
                    <p class="text-sm admin-muted">{{ __('messages.admin_finance.refunds') }}: {{ $summary['currency'] }} {{ number_format((float) $summary['refundTotal'], 2) }} ({{ number_format($summary['refundCount']) }})</p>
                    <p class="text-sm admin-muted">{{ __('messages.admin_finance.status_pending') }}: {{ number_format($summary['pendingCount']) }} · {{ __('messages.admin_finance.status_failed') }}: {{ number_format($summary['failedCount']) }}</p>
                </article>
            @empty
                <p class="admin-empty-state border p-4 text-sm admin-muted">{{ __('messages.admin_finance.no_transactions_for_period') }}</p>
            @endforelse
        </div>
    @elseif ($activePage === 'payment_disputes')
        <div class="grid gap-2 rounded-xl border border-dashed p-5 admin-border">
            <h2 class="font-semibold admin-heading">{{ __('messages.admin_finance.payment_disputes_unavailable_title') }}</h2>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_finance.payment_disputes_unavailable_description') }}</p>
        </div>
    @else
        <div class="admin-empty-state overflow-x-auto border">
            <table class="w-full min-w-[72rem] text-left text-sm">
                <thead class="border-b admin-border">
                    <tr class="text-xs uppercase tracking-[.12em] admin-muted">
                        <th class="px-4 py-3">{{ __('messages.admin_finance.transaction_reference') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.related_booking') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.payer') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.agency') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.provider') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.payment_method') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.transaction_type') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.amount') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.status') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.timestamp') }}</th>
                        <th class="px-4 py-3">{{ __('messages.admin_finance.reconciliation') }}</th>
                        @if ($activePage === 'refunds')
                            <th class="px-4 py-3">{{ __('messages.admin_finance.original_payment') }}</th>
                        @endif
                        @if ($activePage === 'pending_failed_payments')
                            <th class="px-4 py-3">{{ __('messages.admin_finance.retry_state') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y admin-border">
                    @forelse ($financeData['transactions'] as $transaction)
                        @php
                            $transactionStatusLabel = match ($transaction->status->value) {
                                'succeeded' => __('messages.admin_finance.status_succeeded'),
                                'pending' => __('messages.admin_finance.status_pending'),
                                'failed' => __('messages.admin_finance.status_failed'),
                                default => $transaction->status->value,
                            };
                            $transactionTypeLabel = $transaction->type->value === 'refund'
                                ? __('messages.admin_finance.refund')
                                : __('messages.admin_finance.charge');
                            $providerLabel = $transaction->provider === 'agency-reported-offline'
                                ? __('messages.admin_finance.agency_reported_offline')
                                : $transaction->provider;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold admin-heading">{{ $transaction->payment_reference }}</td>
                            <td class="whitespace-nowrap px-4 py-3 admin-heading">
                                {{ $transaction->booking?->booking_reference ?? __('messages.admin_finance.not_recorded') }}
                                @if ($transaction->booking)
                                    <span class="block text-xs admin-muted">{{ __('messages.admin_finance.booking_status') }}: {{ $transaction->booking->status->value }}</span>
                                    <span class="block text-xs admin-muted">{{ __('messages.admin_finance.booking_payment_status') }}: {{ $transaction->booking->payment_status->value }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 admin-muted">{{ $transaction->booking?->traveler?->name ?? __('messages.admin_finance.not_recorded') }}</td>
                            <td class="px-4 py-3 admin-muted">{{ $transaction->booking?->agency?->company_name ?? __('messages.admin_finance.not_recorded') }}</td>
                            <td class="px-4 py-3 admin-muted">
                                {{ $providerLabel }}
                                @if ($transaction->provider_reference)
                                    <span class="block text-xs">{{ $transaction->provider_reference }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 admin-muted">{{ $transaction->payment_method ?: __('messages.admin_finance.not_recorded') }}</td>
                            <td class="px-4 py-3 admin-muted">{{ $transactionTypeLabel }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold admin-heading">{{ $transaction->currency }} {{ number_format((float) $transaction->amount, 2) }}</td>
                            <td class="px-4 py-3 admin-muted">{{ $transactionStatusLabel }}</td>
                            <td class="whitespace-nowrap px-4 py-3 admin-muted">{{ ($transaction->processed_at ?? $transaction->created_at)?->format('Y-m-d H:i') ?? __('messages.admin_finance.not_recorded') }}</td>
                            <td class="px-4 py-3 admin-muted">{{ __('messages.admin_finance.not_recorded') }}</td>
                            @if ($activePage === 'refunds')
                                <td class="px-4 py-3 admin-muted">{{ __('messages.admin_finance.original_payment_not_linked') }}</td>
                            @endif
                            @if ($activePage === 'pending_failed_payments')
                                <td class="px-4 py-3 admin-muted">{{ __('messages.admin_finance.not_recorded') }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $activePage === 'refunds' || $activePage === 'pending_failed_payments' ? 12 : 11 }}" class="px-4 py-8 text-center text-sm admin-muted">
                                {{ __('messages.admin_finance.no_transactions_for_period') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $financeData['transactions']->links() }}
    @endif
</section>
