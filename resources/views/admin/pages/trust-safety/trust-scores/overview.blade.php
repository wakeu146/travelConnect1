<section class="grid min-w-0 gap-6" aria-label="{{ __('messages.admin_nav.pages.trust_score_overview') }}" data-admin-trust-score-overview>
    @include('admin.pages.trust-safety.trust-scores._navigation')

    <div class="grid gap-3 border-b pb-5 admin-border">
        <p class="max-w-4xl text-sm leading-6 admin-muted">{{ __('messages.trust_scores.overview_intro') }}</p>
        <p class="max-w-4xl border-l-2 border-sky-500/60 bg-sky-500/5 px-4 py-3 text-sm leading-6 admin-heading" data-trust-score-model>
            {{ __('messages.trust_scores.scoring_model') }}
        </p>
        <p class="text-xs admin-muted">{{ __('messages.trust_scores.results_count', ['count' => $agencies->total()]) }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'trust_score_overview', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-3 border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <div class="grid min-w-0 gap-1.5">
            <label for="trust-score-search" class="text-xs font-semibold admin-heading">{{ __('messages.trust_scores.search') }}</label>
            <input id="trust-score-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.trust_scores.search_placeholder') }}">
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.trust_safety.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => 'trust_score_overview', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">
                {{ __('messages.trust_safety.clear_filters') }}
            </a>
        </div>
    </form>

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="admin-empty-state overflow-x-auto border" data-trust-score-list>
        <table class="w-full min-w-[64rem] text-left text-sm">
            <thead class="border-b admin-border">
                <tr class="text-xs uppercase tracking-wide admin-muted">
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.agency') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.score_out_of_100') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.average_published_rating') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.published_review_count') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.calculated_at') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.admin_users.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y admin-border">
                @forelse ($agencies as $agency)
                    @php
                        $latestCalculation = $agency->latestTrustScoreLog;
                        $hasCurrentCalculation = $latestCalculation && $latestCalculation->score === $agency->trust_score;
                        $factors = $hasCurrentCalculation ? $latestCalculation->factors : [];
                    @endphp
                    <tr data-trust-score-agency="{{ $agency->id }}">
                        <td class="px-4 py-3 font-semibold admin-heading">{{ $agency->company_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-bold tabular-nums admin-heading">{{ $agency->trust_score }} / 100</td>
                        <td class="px-4 py-3 admin-heading">
                            @if ($hasCurrentCalculation && array_key_exists('average_published_rating', $factors) && $factors['average_published_rating'] !== null)
                                {{ number_format((float) $factors['average_published_rating'], 2) }} / 5
                            @else
                                <span class="admin-muted">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 admin-heading">
                            @if ($hasCurrentCalculation && array_key_exists('published_review_count', $factors))
                                {{ $factors['published_review_count'] }}
                            @else
                                <span class="admin-muted">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs admin-muted">
                            @if ($hasCurrentCalculation)
                                <time datetime="{{ $latestCalculation->calculated_at->toIso8601String() }}">{{ $latestCalculation->calculated_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                                @if (($factors['calculation'] ?? null) === 'average_rating_x_20')
                                    <span class="mt-1 block max-w-64">{{ __('messages.trust_scores.average_rating_x_20') }}</span>
                                @endif
                            @elseif ($latestCalculation)
                                <span>{{ __('messages.trust_scores.calculation_mismatch', ['score' => $latestCalculation->score, 'date' => $latestCalculation->calculated_at->locale(app()->getLocale())->isoFormat('LLL')]) }}</span>
                            @else
                                {{ __('messages.trust_scores.no_calculation_record') }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">
                                {{ __('messages.trust_scores.open_agency') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center admin-muted">{{ __('messages.trust_scores.no_agencies') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="min-w-0 overflow-x-auto">{{ $agencies->links() }}</div>
</section>
