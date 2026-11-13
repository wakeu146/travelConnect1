<section class="admin-empty-state box-border grid min-w-0 gap-4 overflow-hidden border p-4 sm:p-5" aria-labelledby="{{ $chartKey }}-title" data-analytics-chart="{{ $chartKey }}">
    <header class="grid gap-1">
        <h2 id="{{ $chartKey }}-title" class="text-base font-bold admin-heading">{{ $title }}</h2>
        <p class="text-sm leading-6 admin-muted">{{ $description }}</p>
    </header>

    @if (collect($items)->sum('count') > 0)
        <ul class="grid min-w-0 gap-4">
            @foreach ($items as $item)
                <li class="grid min-w-0 gap-1.5">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="min-w-0 flex-1 truncate font-medium admin-heading">
                            @if ($labelType)
                                {{ __("messages.admin_analytics.{$labelType}.".$item[$labelKey]) }}
                            @else
                                {{ $item[$labelKey] }}
                            @endif
                        </span>
                        <span class="shrink-0 font-semibold tabular-nums admin-muted">{{ number_format($item['count']) }}</span>
                    </div>
                    <div class="h-2 w-full min-w-0 overflow-hidden bg-slate-200 dark:bg-slate-700" role="img" aria-label="{{ $item['count'] }}">
                        <div class="h-full max-w-full bg-indigo-600" style="width: min(100%, {{ number_format($item['percent'], 2, '.', '') }}%)"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="border border-dashed p-5 text-sm leading-6 admin-muted">{{ __('messages.admin_analytics.no_activity') }}</p>
    @endif
</section>
