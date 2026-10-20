<section class="grid gap-6" aria-label="{{ __('messages.admin_alerts.record_detail') }}" data-alert-detail>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.dashboard.page', ['page' => 'alerts', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-3 py-2 text-sm font-semibold">
            <x-admin.icon name="chevron_right" class="h-4 w-4 rotate-180" />
            {{ __('messages.admin_alerts.back_to_queue') }}
        </a>
        <span class="text-xs admin-muted">{{ __('messages.admin_alerts.source_id', ['id' => $alert['source_id']]) }}</span>
    </div>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
            {{ session('status') }}
        </p>
    @endif

    @if ($errors->has('status'))
        <p role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ $errors->first('status') }}</p>
    @endif

    <article class="admin-empty-state grid gap-6 border p-5 sm:p-7">
        <header class="grid gap-4 border-b pb-5 admin-border sm:flex sm:items-start sm:justify-between">
            <div class="grid gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold">{{ __('messages.admin_alerts.categories.'.$alert['category']) }}</span>
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold" data-alert-severity="{{ $alert['severity'] }}">{{ __('messages.admin_alerts.severities.'.$alert['severity']) }}</span>
                    <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold">{{ __('messages.admin_alerts.statuses.'.$alert['status']) }}</span>
                </div>
                <h2 class="break-words text-xl font-bold admin-heading">{{ $alert['title'] }}</h2>
                <p class="text-sm admin-muted">{{ __('messages.admin_alerts.related_record') }}: {{ $alert['related_label'] }}</p>
                <p class="text-xs admin-muted">
                    {{ __('messages.admin_alerts.created') }}:
                    <time datetime="{{ \Illuminate\Support\Carbon::parse($alert['created_at'])->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($alert['created_at'])->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                </p>
            </div>
            <a href="{{ route('admin.dashboard.page', ['page' => $alert['management_page'], 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 shrink-0 items-center gap-2 border px-3 py-2 text-sm font-semibold">
                {{ __('messages.admin_alerts.open_management_queue') }}
                <x-admin.icon name="chevron_right" class="h-4 w-4" />
            </a>
        </header>

        <section class="grid gap-2 border-l-4 border-amber-500/70 bg-amber-500/5 p-4" aria-labelledby="alert-reason-title" data-alert-reason>
            <h3 id="alert-reason-title" class="text-sm font-bold admin-heading">{{ __('messages.admin_alerts.reason_heading') }}</h3>
            <p class="break-words whitespace-pre-wrap text-sm leading-6 admin-heading">{{ $alert['reason'] }}</p>
        </section>

        <section class="grid gap-3" aria-labelledby="alert-record-context">
            <h3 id="alert-record-context" class="text-sm font-bold admin-heading">{{ __('messages.admin_alerts.record_context') }}</h3>
            <dl class="grid gap-3 sm:grid-cols-2">
                @foreach ($alert['details'] as $detail)
                    <div class="grid min-w-0 gap-1 border-b pb-3 admin-border">
                        <dt class="text-xs font-semibold admin-muted">{{ $detail['label'] }}</dt>
                        <dd class="break-words whitespace-pre-wrap text-sm admin-heading">{{ $detail['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="grid gap-3 border-t pt-5 admin-border" aria-labelledby="alert-actions-title">
            <h3 id="alert-actions-title" class="text-sm font-bold admin-heading">{{ __('messages.admin_alerts.actions_title') }}</h3>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_alerts.actions_description') }}</p>
            @if ($alert['status'] !== 'resolved')
                <div class="flex flex-wrap gap-2">
                    @if ($alert['status'] === 'open')
                        <form method="POST" action="{{ route('admin.alerts.acknowledge', ['source' => $alert['source_type'], 'sourceId' => $alert['source_id'], 'lang' => app()->getLocale()]) }}">
                            @csrf
                            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                                <x-admin.icon name="shield_check" class="h-4 w-4" />
                                {{ __('messages.admin_alerts.acknowledge') }}
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.alerts.resolve', ['source' => $alert['source_type'], 'sourceId' => $alert['source_id'], 'lang' => app()->getLocale()]) }}">
                        @csrf
                        <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                            <x-admin.icon name="shield_check" class="h-4 w-4" />
                            {{ __('messages.admin_alerts.resolve') }}
                        </button>
                    </form>
                </div>
            @else
                <p class="text-sm font-semibold admin-muted">{{ __('messages.admin_alerts.resolved_notice') }}</p>
            @endif
        </section>
    </article>

    <section class="admin-empty-state grid gap-4 border p-5 sm:p-6" aria-labelledby="alert-history-title">
        <header class="grid gap-1">
            <h2 id="alert-history-title" class="text-base font-bold admin-heading">{{ __('messages.admin_alerts.audit_history') }}</h2>
            <p class="text-sm leading-5 admin-muted">{{ __('messages.admin_alerts.audit_history_description') }}</p>
        </header>
        @if ($alert['actions']->isNotEmpty())
            <ol class="grid divide-y admin-border">
                @foreach ($alert['actions'] as $action)
                    <li class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto] sm:items-center">
                        <p class="text-sm font-semibold admin-heading">
                            {{ __('messages.admin_alerts.actions.'.$action->action) }}
                            <span class="font-normal admin-muted">{{ __('messages.admin_alerts.status_change', ['before' => __('messages.admin_alerts.statuses.'.$action->status_before), 'after' => __('messages.admin_alerts.statuses.'.$action->status_after)]) }}</span>
                        </p>
                        <p class="text-xs admin-muted">
                            {{ $action->actor?->name ?? __('messages.admin_alerts.unknown_admin') }}
                            · <time datetime="{{ $action->created_at->toIso8601String() }}">{{ $action->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                        </p>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="text-sm admin-muted" data-alert-audit-empty>{{ __('messages.admin_alerts.no_audit_history') }}</p>
        @endif
    </section>
</section>
