@props(['value' => 0, 'label' => null])

<div {{ $attributes }}>
    @if ($label)
        <div class="mb-2 flex items-center justify-between gap-3 text-xs">
            <span class="agency-text-subtle">{{ $label }}</span>
            <span class="agency-text-primary font-semibold tabular-nums">{{ min(100, max(0, (int) $value)) }}%</span>
        </div>
    @endif
    <div class="agency-progress-track h-2 overflow-hidden rounded-full" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, max(0, (int) $value)) }}" @if ($label) aria-label="{{ $label }}" @endif>
        <div class="agency-progress-bar h-full rounded-full transition-[width] duration-300" style="width: {{ min(100, max(0, (int) $value)) }}%"></div>
    </div>
</div>