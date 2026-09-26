@props(['label', 'value', 'note' => null, 'tone' => 'neutral'])

<section {{ $attributes->class(['agency-panel agency-kpi rounded-2xl border p-5 shadow-sm']) }}>
    <p class="agency-text-subtle text-sm font-semibold">{{ $label }}</p>
    <p class="mt-4 text-3xl font-bold tabular-nums {{ $tone === 'success' ? 'agency-text-success' : ($tone === 'warning' ? 'agency-text-warning' : '') }}">{{ $value }}</p>
    @if ($note)
        <p class="agency-text-subtle mt-1 text-xs">{{ $note }}</p>
    @endif
    @if (isset($icon))
        <span class="agency-icon agency-icon-{{ $tone }} mt-4 inline-flex h-9 w-9 items-center justify-center rounded-lg">{{ $icon }}</span>
    @endif
</section>