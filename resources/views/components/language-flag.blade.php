@props(['locale'])

@if ($locale === 'fr')
    <svg {{ $attributes->merge(['class' => 'h-3 w-4 shrink-0 rounded-[2px] shadow-sm']) }} viewBox="0 0 30 20" aria-hidden="true" focusable="false">
        <rect width="10" height="20" fill="#002395" />
        <rect x="10" width="10" height="20" fill="#fff" />
        <rect x="20" width="10" height="20" fill="#ed2939" />
    </svg>
@else
    <svg {{ $attributes->merge(['class' => 'h-3 w-4 shrink-0 rounded-[2px] shadow-sm']) }} viewBox="0 0 60 30" aria-hidden="true" focusable="false">
        <rect width="60" height="30" fill="#17356b" />
        <path d="M0 0 60 30M60 0 0 30" stroke="#fff" stroke-width="7" />
        <path d="M0 0 60 30M60 0 0 30" stroke="#c8102e" stroke-width="3" />
        <path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="11" />
        <path d="M30 0v30M0 15h60" stroke="#c8102e" stroke-width="6" />
    </svg>
@endif
