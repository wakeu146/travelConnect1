@props(['title', 'description' => null])

<div {{ $attributes->class(['agency-empty-state rounded-xl border border-dashed p-7 text-center']) }}>
    <p class="agency-text-primary text-sm font-semibold">{{ $title }}</p>
    @if ($description)
        <p class="agency-text-subtle mt-2 text-sm">{{ $description }}</p>
    @endif
    @if (isset($actions))
        <div class="mt-4 flex justify-center gap-2">{{ $actions }}</div>
    @endif
</div>