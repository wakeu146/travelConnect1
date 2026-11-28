@props([
    'variant' => 'light',
])

@php
    $logoClasses = $attributes->has('class')
        ? 'w-auto max-w-full object-contain'
        : 'h-10 w-auto max-w-full object-contain';
@endphp

@if ($variant === 'both')
    <img
        data-brand-logo-light
        src="{{ asset('images/logos/travelconnect1.png') }}"
        alt="TravelConnect logo"
        {{ $attributes->merge(['class' => $logoClasses]) }}
    >
    <img
        data-brand-logo-dark
        src="{{ asset('images/logos/travelconnect.png') }}"
        alt="TravelConnect logo"
        {{ $attributes->merge(['class' => 'hidden '.$logoClasses]) }}
    >
@elseif ($variant === 'dark')
    <img
        src="{{ asset('images/logos/travelconnect.png') }}"
        alt="TravelConnect logo"
        {{ $attributes->merge(['class' => $logoClasses]) }}
    >
@else
    <img
        src="{{ asset('images/logos/travelconnect1.png') }}"
        alt="TravelConnect logo"
        {{ $attributes->merge(['class' => $logoClasses]) }}
    >
@endif
