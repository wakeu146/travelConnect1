@props(['muted' => false])

<div {{ $attributes->class([$muted ? 'agency-panel-muted' : 'agency-panel', 'rounded-2xl border p-5 shadow-sm']) }}>
    {{ $slot }}
</div>