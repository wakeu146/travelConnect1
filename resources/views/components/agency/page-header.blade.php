@props(['eyebrow', 'title', 'description' => null])

<header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ $eyebrow }}</p>
        <h1 class="mt-2 font-serif text-3xl tracking-tight sm:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="agency-text-subtle mt-2 max-w-2xl text-sm leading-6">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</header>