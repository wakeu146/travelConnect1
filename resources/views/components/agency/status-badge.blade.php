@props(['label', 'tone' => 'neutral'])

<span {{ $attributes->class(['agency-pill', 'agency-pill-'.$tone, 'inline-flex w-fit items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em]']) }}>
    {{ $label }}
</span>