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

    <div class="grid min-w-0 gap-4 md:grid-cols-2" data-trust-score-list>
        @forelse ($agencies as $agency)
            @php
                $latestCalculation = $agency->latestTrustScoreLog;
                $hasCurrentCalculation = $latestCalculation && $latestCalculation->score === $agency->trust_score;
                $factors = $hasCurrentCalculation ? $latestCalculation->factors : [];
            @endphp
            <article class="admin-empty-state grid min-w-0 content-start gap-4 border p-4 sm:p-5" data-trust-score-agency="{{ $agency->id }}">
                <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                    <div class="grid min-w-0 gap-1">
                        <h2 class="break-words text-base font-bold admin-heading">{{ $agency->company_name }}</h2>
                        <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="w-fit break-words text-xs font-semibold admin-accent underline decoration-current/30 underline-offset-4">
                            {{ __('messages.trust_scores.open_agency') }}
                        </a>
                    </div>
                    <div class="grid shrink-0 justify-items-end gap-1">
                        <span class="text-3xl font-extrabold tabular-nums admin-heading">{{ $agency->trust_score }}</span>
                        <span class="text-[11px] admin-muted">{{ __('messages.trust_scores.score_out_of_100') }}</span>
                    </div>
                </header>

                @if ($hasCurrentCalculation)
                    <dl class="grid min-w-0 gap-3 border-t pt-4 admin-border sm:grid-cols-2">
                        @if (array_key_exists('average_published_rating', $factors) && $factors['average_published_rating'] !== null)
                            <div class="grid min-w-0 gap-1">
                                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_scores.average_published_rating') }}</dt>
                                <dd class="break-words text-sm font-semibold admin-heading">{{ number_format((float) $factors['average_published_rating'], 2) }} / 5</dd>
                            </div>
                        @endif
                        @if (array_key_exists('published_review_count', $factors))
                            <div class="grid min-w-0 gap-1">
                                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_scores.published_review_count') }}</dt>
                                <dd class="break-words text-sm font-semibold admin-heading">{{ $factors['published_review_count'] }}</dd>
                            </div>
                        @endif
                        <div class="grid min-w-0 gap-1 sm:col-span-2">
                            <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_scores.calculated_at') }}</dt>
                            <dd class="break-words text-sm admin-heading"><time datetime="{{ $latestCalculation->calculated_at->toIso8601String() }}">{{ $latestCalculation->calculated_at->locale(app()->getLocale())->isoFormat('LLL') }}</time></dd>
                        </div>
                        @if (($factors['calculation'] ?? null) === 'average_rating_x_20')
                            <div class="grid min-w-0 gap-1 sm:col-span-2">
                                <dt class="text-xs font-semibold admin-muted">{{ __('messages.trust_scores.calculation') }}</dt>
                                <dd class="break-words text-sm leading-5 admin-heading">{{ __('messages.trust_scores.average_rating_x_20') }}</dd>
                            </div>
                        @endif
                    </dl>
                @elseif ($latestCalculation)
                    <p class="border-t pt-4 text-sm leading-5 admin-muted admin-border">
                        {{ __('messages.trust_scores.calculation_mismatch', ['score' => $latestCalculation->score, 'date' => $latestCalculation->calculated_at->locale(app()->getLocale())->isoFormat('LLL')]) }}
                    </p>
                @else
                    <p class="border-t pt-4 text-sm leading-5 admin-muted admin-border">{{ __('messages.trust_scores.no_calculation_record') }}</p>
                @endif
            </article>
        @empty
            <p class="admin-empty-state border p-6 text-sm admin-muted md:col-span-2">{{ __('messages.trust_scores.no_agencies') }}</p>
        @endforelse
    </div>

    <div class="min-w-0 overflow-x-auto">{{ $agencies->links() }}</div>
</section>
