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

    <div class="admin-empty-state overflow-x-auto border" data-attention-agency-list>
        <table class="w-full min-w-[68rem] text-left text-sm">
            <thead class="border-b admin-border">
                <tr class="text-xs uppercase tracking-wide admin-muted">
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.agency') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.score_out_of_100') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_scores.attention_reasons') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.admin_users.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y admin-border">
                @forelse ($agencies as $agency)
                    <tr data-attention-agency="{{ $agency->id }}">
                        <td class="px-4 py-3 font-semibold admin-heading">{{ $agency->company_name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums admin-heading">{{ $agency->trust_score }} / 100</td>
                        <td class="px-4 py-3">
                            <ul class="grid gap-1">
                                @if ($agency->verification_status->value !== 'verified')
                                    <li class="text-xs admin-heading">{{ __('messages.trust_scores.verification_attention', ['status' => __('messages.admin_agencies.verification_statuses.'.$agency->verification_status->value)]) }}</li>
                                @endif
                                @if ($agency->operating_status === 'suspended')
                                    <li class="text-xs admin-heading">{{ __('messages.trust_scores.suspension_attention') }}</li>
                                @endif
                                @if ($agency->flagged_reviews_count > 0)
                                    <li class="text-xs admin-heading">{{ __('messages.trust_scores.flagged_reviews_attention', ['count' => $agency->flagged_reviews_count]) }}</li>
                                @endif
                                @if ($agency->hidden_reviews_count > 0)
                                    <li class="text-xs admin-heading">{{ __('messages.trust_scores.hidden_reviews_attention', ['count' => $agency->hidden_reviews_count]) }}</li>
                                @endif
                                @if ($agency->active_reports_count > 0)
                                    <li class="text-xs admin-heading">{{ __('messages.trust_scores.active_reports_attention', ['count' => $agency->active_reports_count]) }}</li>
                                @endif
                                @if ($agency->active_complaints_count > 0)
                                    <li class="text-xs admin-heading">{{ __('messages.trust_scores.active_complaints_attention', ['count' => $agency->active_complaints_count]) }}</li>
                                @endif
                            </ul>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex min-w-40 flex-wrap gap-2">
                                <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_scores.open_agency') }}</a>
                                @if ($agency->verification_status->value !== 'verified')
                                    @php($verificationQueue = $agency->verification_status->value === 'rejected' ? 'rejected' : 'verification_pending')
                                    <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'queue' => $verificationQueue, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_scores.open_verification') }}</a>
                                @endif
                                @if ($agency->flagged_reviews_count > 0 || $agency->hidden_reviews_count > 0)
                                    <a href="{{ route('admin.dashboard.page', ['page' => 'reviews_ratings', 'search' => $agency->company_name, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_scores.open_reviews') }}</a>
                                @endif
                                @if ($agency->active_reports_count > 0)
                                    <a href="{{ route('admin.dashboard.page', ['page' => 'reported_content', 'search' => $agency->company_name, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_scores.view_reports') }}</a>
                                @endif
                                @if ($agency->active_complaints_count > 0)
                                    <a href="{{ route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'search' => $agency->company_name, 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_scores.open_complaints') }}</a>
                                    <a href="{{ route('admin.agencies.show', ['agency' => $agency->id, 'lang' => app()->getLocale()]) }}#agency-bookings" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_scores.open_bookings') }}</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center admin-muted">{{ __('messages.trust_scores.no_attention') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="min-w-0 overflow-x-auto">{{ $agencies->links() }}</div>
</section>
