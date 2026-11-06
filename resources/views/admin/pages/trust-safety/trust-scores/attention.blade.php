<section class="grid min-w-0 gap-6" aria-label="{{ __('messages.admin_nav.pages.agencies_requiring_attention') }}" data-admin-agencies-requiring-attention>
    @include('admin.pages.trust-safety.trust-scores._navigation')

    <div class="flex min-w-0 flex-col gap-2 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
        <p class="max-w-4xl text-sm leading-6 admin-muted">{{ __('messages.trust_scores.attention_intro') }}</p>
        <p class="shrink-0 text-xs admin-muted">{{ __('messages.trust_scores.results_count', ['count' => $agencies->total()]) }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'agencies_requiring_attention', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-3 border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <div class="grid min-w-0 gap-1.5">
            <label for="attention-search" class="text-xs font-semibold admin-heading">{{ __('messages.trust_scores.search') }}</label>
            <input id="attention-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.trust_scores.search_placeholder') }}">
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.trust_safety.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => 'agencies_requiring_attention', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">
                {{ __('messages.trust_safety.clear_filters') }}
            </a>
        </div>
    </form>

    <div class="grid min-w-0 gap-4 md:grid-cols-2" data-attention-agency-list>
        @forelse ($agencies as $agency)
            <article class="admin-empty-state grid min-w-0 content-start gap-4 border p-4 sm:p-5" data-attention-agency="{{ $agency->id }}">
                <header class="grid min-w-0 gap-3">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                        <h2 class="min-w-0 break-words text-base font-bold admin-heading">{{ $agency->company_name }}</h2>
                        <span class="shrink-0 text-sm font-bold tabular-nums admin-heading">{{ __('messages.trust_scores.score_value', ['score' => $agency->trust_score]) }}</span>
                    </div>
                    <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="w-fit break-words text-xs font-semibold admin-accent underline decoration-current/30 underline-offset-4">
                        {{ __('messages.trust_scores.open_agency') }}
                    </a>
                </header>

                <ul class="grid min-w-0 gap-2 border-t pt-4 admin-border" aria-label="{{ __('messages.trust_scores.attention_reasons') }}">
                    @if ($agency->verification_status->value !== 'verified')
                        <li class="break-words text-sm admin-heading">{{ __('messages.trust_scores.verification_attention', ['status' => __('messages.admin_agencies.verification_statuses.'.$agency->verification_status->value)]) }}</li>
                    @endif
                    @if ($agency->operating_status === 'suspended')
                        <li class="break-words text-sm admin-heading">{{ __('messages.trust_scores.suspension_attention') }}</li>
                    @endif
                    @if ($agency->flagged_reviews_count > 0)
                        <li class="break-words text-sm admin-heading">{{ __('messages.trust_scores.flagged_reviews_attention', ['count' => $agency->flagged_reviews_count]) }}</li>
                    @endif
                    @if ($agency->hidden_reviews_count > 0)
                        <li class="break-words text-sm admin-heading">{{ __('messages.trust_scores.hidden_reviews_attention', ['count' => $agency->hidden_reviews_count]) }}</li>
                    @endif
                    @if ($agency->active_reports_count > 0)
                        <li class="break-words text-sm admin-heading">{{ __('messages.trust_scores.active_reports_attention', ['count' => $agency->active_reports_count]) }}</li>
                    @endif
                    @if ($agency->active_complaints_count > 0)
                        <li class="break-words text-sm admin-heading">{{ __('messages.trust_scores.active_complaints_attention', ['count' => $agency->active_complaints_count]) }}</li>
                    @endif
                </ul>

                <div class="flex min-w-0 flex-wrap gap-2 border-t pt-4 admin-border">
                    @if ($agency->verification_status->value !== 'verified')
                        @php($verificationQueue = $agency->verification_status->value === 'rejected' ? 'rejected' : 'verification_pending')
                        <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'queue' => $verificationQueue, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 min-w-0 items-center break-words border px-3 py-1.5 text-xs font-semibold">
                            {{ __('messages.trust_scores.open_verification') }}
                        </a>
                    @endif
                    @if ($agency->flagged_reviews_count > 0 || $agency->hidden_reviews_count > 0)
                        <a href="{{ route('admin.dashboard.page', ['page' => 'reviews_ratings', 'search' => $agency->company_name, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 min-w-0 items-center break-words border px-3 py-1.5 text-xs font-semibold">
                            {{ __('messages.trust_scores.open_reviews') }}
                        </a>
                    @endif
                    @if ($agency->active_reports_count > 0)
                        <a href="{{ route('admin.dashboard.page', ['page' => 'reported_content', 'search' => $agency->company_name, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 min-w-0 items-center break-words border px-3 py-1.5 text-xs font-semibold">
                            {{ __('messages.trust_scores.view_reports') }}
                        </a>
                    @endif
                    @if ($agency->active_complaints_count > 0)
                        <a href="{{ route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'search' => $agency->company_name, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 min-w-0 items-center break-words border px-3 py-1.5 text-xs font-semibold">
                            {{ __('messages.trust_scores.open_complaints') }}
                        </a>
                        <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}#agency-bookings" class="admin-nav-link inline-flex min-h-9 min-w-0 items-center break-words border px-3 py-1.5 text-xs font-semibold">
                            {{ __('messages.trust_scores.open_bookings') }}
                        </a>
                    @endif
                </div>
            </article>
        @empty
            <p class="admin-empty-state border p-6 text-sm admin-muted md:col-span-2">{{ __('messages.trust_scores.no_attention') }}</p>
        @endforelse
    </div>

    <div class="min-w-0 overflow-x-auto">{{ $agencies->links() }}</div>
</section>
