<section class="admin-empty-state box-border grid min-w-0 gap-4 overflow-hidden border p-4 sm:p-5" aria-labelledby="{{ $chartKey }}-title" data-analytics-chart="{{ $chartKey }}" data-analytics-chart-maximum="{{ $chart['maximum'] }}">
    <header class="grid gap-1">
        <h2 id="{{ $chartKey }}-title" class="text-base font-bold admin-heading">{{ $title }}</h2>
        <p class="text-sm leading-6 admin-muted">{{ $description }}</p>
    </header>

    @if ($chart['hasActivity'])
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs font-semibold admin-muted" aria-label="{{ $title }}">
            @foreach ($chart['series'] as $series)
                <span class="inline-flex items-center gap-2">
                    <span class="h-2 w-5" style="background-color: {{ $series['color'] }}"></span>
                    {{ __('messages.admin_analytics.series.'.$series['key']) }}
                </span>
            @endforeach
        </div>
        <div class="relative min-w-0" data-analytics-plot>
            <svg viewBox="0 0 800 310" preserveAspectRatio="none" role="group" aria-labelledby="{{ $chartKey }}-title {{ $chartKey }}-description" data-chart-periods="{{ json_encode($chart['periods']) }}" class="block h-64 w-full overflow-visible sm:h-72">
                <desc id="{{ $chartKey }}-description">{{ $description }}</desc>
                <defs>
                    @foreach ($chart['series'] as $series)
                        <linearGradient id="{{ $chartKey }}-fill-{{ $loop->index }}" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="{{ $series['color'] }}" stop-opacity=".16" />
                            <stop offset="100%" stop-color="{{ $series['color'] }}" stop-opacity="0" />
                        </linearGradient>
                    @endforeach
                </defs>
                <g fill="none" stroke="currentColor" stroke-opacity=".14" stroke-width="1">
                    @foreach ($chart['ticks'] as $tick)
                        <path d="M70 {{ $tick['y'] }}H776" />
                    @endforeach
                    <path d="M70 30V250" />
                </g>
                <g fill="currentColor" font-size="13" class="admin-muted">
                    @foreach ($chart['ticks'] as $tick)
                        <text x="60" y="{{ $tick['y'] + 4 }}" text-anchor="end" data-analytics-y-tick="{{ $tick['value'] }}">{{ $tick['label'] }}</text>
                    @endforeach
                    <text x="70" y="278" text-anchor="start">{{ $chart['dates'][0] }}</text>
                    <text x="423" y="278" text-anchor="middle">{{ $chart['dates'][(int) floor((count($chart['dates']) - 1) / 2)] }}</text>
                    <text x="776" y="278" text-anchor="end">{{ $chart['dates'][count($chart['dates']) - 1] }}</text>
                </g>
                @foreach ($chart['series'] as $series)
                    <path d="{{ $series['areaPath'] }}" fill="url(#{{ $chartKey }}-fill-{{ $loop->index }})" />
                @endforeach
                @foreach ($chart['series'] as $series)
                    <path d="{{ $series['path'] }}" fill="none" stroke="{{ $series['color'] }}" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" data-analytics-series="{{ $series['key'] }}" data-analytics-series-label="{{ __('messages.admin_analytics.series.'.$series['key']) }}" data-analytics-series-values="{{ implode(',', $series['values']) }}" />
                    @foreach ($series['points'] as $index => $point)
                        <circle cx="{{ number_format($point['x'], 2, '.', '') }}" cy="{{ number_format($point['y'], 2, '.', '') }}" r="4" fill="{{ $series['color'] }}" stroke="white" stroke-width="2" visibility="hidden" data-analytics-point="{{ $index }}" />
                    @endforeach
                @endforeach
                <line x1="70" x2="70" y1="30" y2="250" stroke="currentColor" stroke-dasharray="4 4" stroke-opacity=".55" visibility="hidden" data-analytics-cursor />
                <rect x="70" y="30" width="706" height="220" fill="transparent" tabindex="0" role="application" aria-label="{{ __('messages.admin_analytics.interactive_chart') }}" data-analytics-chart-interaction />
            </svg>
            <div class="pointer-events-none absolute left-0 top-0 z-10 hidden max-w-[18rem] border p-3 text-xs shadow-lg admin-empty-state" role="status" aria-live="polite" data-analytics-tooltip></div>
        </div>
    @else
        <p class="border border-dashed p-5 text-sm leading-6 admin-muted">{{ __('messages.admin_analytics.no_activity') }}</p>
    @endif
</section>

@once
    <script>
        (() => {
            const numberFormatter = new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 3,
                maximumFractionDigits: 3,
            });

            const formatDate = (date) => {
                const [year, month, day] = date.split('-').map(Number);
                return new Intl.DateTimeFormat(undefined, {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric',
                }).format(new Date(year, month - 1, day));
            };

            document.querySelectorAll('[data-analytics-plot]').forEach((plot) => {
                const svg = plot.querySelector('svg');
                const interaction = svg?.querySelector('[data-analytics-chart-interaction]');
                const cursor = svg?.querySelector('[data-analytics-cursor]');
                const tooltip = plot.querySelector('[data-analytics-tooltip]');
                if (!svg || !interaction || !cursor || !tooltip) return;

                const periods = JSON.parse(svg.dataset.chartPeriods);
                const series = [...svg.querySelectorAll('[data-analytics-series]')].map((line) => ({
                    label: line.dataset.analyticsSeriesLabel,
                    color: line.getAttribute('stroke'),
                    values: line.dataset.analyticsSeriesValues.split(',').map(Number),
                }));
                const markers = [...svg.querySelectorAll('[data-analytics-point]')];
                let activeIndex = 0;

                const showTooltip = (index, x, y) => {
                    activeIndex = Math.max(0, Math.min(periods.length - 1, index));
                    const period = periods[activeIndex];
                    const heading = document.createElement('p');
                    heading.className = 'font-semibold admin-heading';
                    heading.textContent = period.from === period.to
                        ? formatDate(period.from)
                        : `${formatDate(period.from)} – ${formatDate(period.to)}`;

                    const values = document.createElement('div');
                    values.className = 'mt-2 grid gap-1.5';
                    series.forEach((item) => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between gap-4';

                        const label = document.createElement('span');
                        label.className = 'inline-flex min-w-0 items-center gap-2 admin-muted';

                        const swatch = document.createElement('span');
                        swatch.className = 'h-2 w-2 shrink-0';
                        swatch.style.backgroundColor = item.color;

                        const name = document.createElement('span');
                        name.className = 'truncate';
                        name.textContent = item.label;
                        label.append(swatch, name);

                        const value = document.createElement('span');
                        value.className = 'shrink-0 font-semibold tabular-nums admin-heading';
                        value.textContent = numberFormatter.format(item.values[activeIndex] ?? 0);
                        row.append(label, value);
                        values.append(row);
                    });

                    tooltip.replaceChildren(heading, values);
                    tooltip.classList.remove('hidden');

                    const left = (x / 800) * plot.clientWidth;
                    const top = (y / 310) * plot.clientHeight;
                    const tooltipLeft = Math.max(4, Math.min(left + 12, plot.clientWidth - tooltip.offsetWidth - 4));
                    const tooltipTop = Math.max(4, Math.min(top - tooltip.offsetHeight - 12, plot.clientHeight - tooltip.offsetHeight - 4));
                    tooltip.style.left = `${tooltipLeft}px`;
                    tooltip.style.top = `${tooltipTop}px`;

                    const pointX = periods.length === 1 ? 423 : 70 + (activeIndex * 706 / (periods.length - 1));
                    cursor.setAttribute('x1', pointX);
                    cursor.setAttribute('x2', pointX);
                    cursor.setAttribute('visibility', 'visible');
                    markers.forEach((marker) => {
                        marker.setAttribute('visibility', Number(marker.dataset.analyticsPoint) === activeIndex ? 'visible' : 'hidden');
                    });
                };

                const indexAtPointer = (event) => {
                    const point = svg.createSVGPoint();
                    point.x = event.clientX;
                    point.y = event.clientY;
                    const chartPoint = point.matrixTransform(svg.getScreenCTM().inverse());
                    const fraction = Math.max(0, Math.min(1, (chartPoint.x - 70) / 706));

                    return Math.round(fraction * (periods.length - 1));
                };

                interaction.addEventListener('pointermove', (event) => {
                    const point = svg.createSVGPoint();
                    point.x = event.clientX;
                    point.y = event.clientY;
                    const chartPoint = point.matrixTransform(svg.getScreenCTM().inverse());
                    showTooltip(indexAtPointer(event), chartPoint.x, chartPoint.y);
                });
                interaction.addEventListener('pointerleave', (event) => {
                    if (event.pointerType === 'touch') return;

                    tooltip.classList.add('hidden');
                    cursor.setAttribute('visibility', 'hidden');
                    markers.forEach((marker) => marker.setAttribute('visibility', 'hidden'));
                });
                interaction.addEventListener('focus', () => showTooltip(activeIndex, 70, 30));
                interaction.addEventListener('blur', () => {
                    tooltip.classList.add('hidden');
                    cursor.setAttribute('visibility', 'hidden');
                    markers.forEach((marker) => marker.setAttribute('visibility', 'hidden'));
                });
                interaction.addEventListener('keydown', (event) => {
                    let nextIndex = activeIndex;
                    if (event.key === 'ArrowRight') nextIndex++;
                    else if (event.key === 'ArrowLeft') nextIndex--;
                    else if (event.key === 'Home') nextIndex = 0;
                    else if (event.key === 'End') nextIndex = periods.length - 1;
                    else return;

                    event.preventDefault();
                    const pointX = periods.length === 1 ? 423 : 70 + (nextIndex * 706 / (periods.length - 1));
                    showTooltip(nextIndex, pointX, 30);
                });
            });
        })();
    </script>
@endonce
