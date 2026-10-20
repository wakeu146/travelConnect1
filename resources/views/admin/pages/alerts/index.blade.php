@if ($alertDetail)
    @include('admin.pages.alerts.show', ['alert' => $alertDetail])
@else
    <section class="grid gap-6" aria-label="{{ __('messages.admin_alerts.title') }}" data-admin-alerts>
        <div class="flex flex-col gap-4 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
            <div class="grid max-w-3xl gap-2">
                <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_alerts.introduction') }}</p>
                <p class="text-xs admin-muted">{{ __('messages.admin_alerts.results_count', ['count' => $alerts->total()]) }}</p>
            </div>
        </div>

        @if (session('status'))
            <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                {{ session('status') }}
            </p>
        @endif

        <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'alerts', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="grid min-w-0 gap-1.5 xl:col-span-2">
                <label for="alerts-search" class="text-xs font-semibold admin-heading">{{ __('messages.admin_alerts.search') }}</label>
                <input id="alerts-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.admin_alerts.search_placeholder') }}">
            </div>
            <div class="grid min-w-0 gap-1.5">
                <label for="alerts-status" class="text-xs font-semibold admin-heading">{{ __('messages.admin_alerts.status') }}</label>
                <select id="alerts-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                    <option value="all">{{ __('messages.admin_alerts.all_statuses') }}</option>
                    @foreach (['open', 'acknowledged', 'resolved'] as $status)
                        <option value="{{ $status }}" @selected(request('status', 'all') === $status)>{{ __('messages.admin_alerts.statuses.'.$status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid min-w-0 gap-1.5">
                <label for="alerts-severity" class="text-xs font-semibold admin-heading">{{ __('messages.admin_alerts.severity') }}</label>
                <select id="alerts-severity" name="severity" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                    <option value="all">{{ __('messages.admin_alerts.all_severities') }}</option>
                    @foreach (['low', 'medium', 'high', 'critical'] as $severity)
                        <option value="{{ $severity }}" @selected(request('severity', 'all') === $severity)>{{ __('messages.admin_alerts.severities.'.$severity) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid min-w-0 gap-1.5 xl:col-span-2">
                <label for="alerts-category" class="text-xs font-semibold admin-heading">{{ __('messages.admin_alerts.category') }}</label>
                <select id="alerts-category" name="category" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                    <option value="all">{{ __('messages.admin_alerts.all_categories') }}</option>
                    @foreach (['agency_verification', 'reported_content', 'failed_payment', 'failed_job', 'suspicious_activity'] as $category)
                        <option value="{{ $category }}" @selected(request('category', 'all') === $category)>{{ __('messages.admin_alerts.categories.'.$category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex min-w-0 flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
                <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                    <x-admin.icon name="search" class="h-4 w-4" />
                    {{ __('messages.admin_alerts.apply_filters') }}
                </button>
                <a href="{{ route('admin.dashboard.page', ['page' => 'alerts', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">
                    {{ __('messages.admin_alerts.clear_filters') }}
                </a>
            </div>
        </form>

        @if ($errors->has('status'))
            <p role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ $errors->first('status') }}</p>
        @endif

        <aside class="admin-empty-state grid gap-2 border p-4" aria-label="{{ __('messages.admin_alerts.source_coverage') }}">
            <h2 class="text-sm font-bold admin-heading">{{ __('messages.admin_alerts.source_coverage') }}</h2>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_alerts.untracked_sources') }}</p>
        </aside>

        @if ($alerts->isNotEmpty())
            <div class="admin-empty-state overflow-x-auto border">
                <table class="w-full min-w-[52rem] text-left text-sm">
                    <thead class="border-b admin-border">
                        <tr class="text-xs uppercase tracking-wide admin-muted">
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_alerts.alert') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_alerts.category') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_alerts.severity') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_alerts.status') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_alerts.created') }}</th>
                            <th scope="col" class="px-4 py-3 font-semibold"><span class="sr-only">{{ __('messages.admin_alerts.open_record') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y admin-border">
                        @foreach ($alerts as $alert)
                            @php
                                $detailUrl = route('admin.dashboard.page', [
                                    'page' => 'alerts',
                                    'source' => $alert->source_type,
                                    'source_id' => $alert->source_id,
                                    'lang' => app()->getLocale(),
                                ]);
                                $severityClasses = match ($alert->severity) {
                                    'critical' => 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200',
                                    'high' => 'border-orange-500/40 bg-orange-500/10 text-orange-800 dark:text-orange-200',
                                    'medium' => 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200',
                                    default => 'border-slate-500/40 bg-slate-500/10 text-slate-700 dark:text-slate-200',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="max-w-md px-4 py-4">
                                    <a href="{{ $detailUrl }}" class="break-words font-semibold admin-heading underline decoration-transparent underline-offset-4 hover:decoration-current focus-visible:decoration-current">{{ $alert->title }}</a>
                                    <p class="mt-1 break-words text-xs leading-5 admin-muted">{{ $alert->related_label }}</p>
                                </td>
                                <td class="px-4 py-4 admin-muted">{{ __('messages.admin_alerts.categories.'.$alert->category) }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex border px-2 py-1 text-xs font-bold {{ $severityClasses }}">{{ __('messages.admin_alerts.severities.'.$alert->severity) }}</span>
                                </td>
                                <td class="px-4 py-4 admin-muted">{{ __('messages.admin_alerts.statuses.'.$alert->status) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 admin-muted">
                                    <time datetime="{{ \Illuminate\Support\Carbon::parse($alert->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($alert->created_at)->locale(app()->getLocale())->diffForHumans() }}</time>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ $detailUrl }}" class="admin-nav-link inline-flex min-h-9 items-center gap-1.5 px-2 text-xs font-semibold">
                                        {{ __('messages.admin_alerts.open_record') }}
                                        <x-admin.icon name="chevron_right" class="h-3.5 w-3.5" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>{{ $alerts->links() }}</div>
        @else
            <div class="admin-empty-state grid justify-items-center gap-3 border p-8 text-center" data-alerts-empty>
                <span class="admin-empty-icon flex h-11 w-11 items-center justify-center">
                    <x-admin.icon name="bell" class="h-5 w-5" />
                </span>
                <div class="grid gap-1">
                    <h2 class="text-sm font-bold admin-heading">{{ __('messages.admin_alerts.empty_title') }}</h2>
                    <p class="max-w-xl text-sm leading-6 admin-muted">{{ __('messages.admin_alerts.empty_description') }}</p>
                </div>
            </div>
        @endif
    </section>
@endif
