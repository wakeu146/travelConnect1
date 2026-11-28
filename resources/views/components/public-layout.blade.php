@props([
    'title' => 'TravelConnect',
    'description' => 'Discover trusted travel agencies with TravelConnect.',
    'image' => null,
    'robots' => 'index, follow',
    'schema' => [],
])
@php
    $appUrl = \App\Support\CanonicalUrl::base();
    $pagePath = request()->getPathInfo();
    $canonical = $appUrl.$pagePath;
    $socialImage = $image ?: $appUrl.'/images/logos/travelconnect1.png';
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => array_merge([
            [
                '@type' => 'Organization',
                '@id' => $appUrl.'/#organization',
                'name' => 'TravelConnect',
                'url' => $appUrl,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $appUrl.'/images/logos/travelconnect1.png',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $appUrl.'/#website',
                'url' => $appUrl,
                'name' => 'TravelConnect',
                'publisher' => ['@id' => $appUrl.'/#organization'],
                'inLanguage' => ['en', 'fr'],
            ],
        ], $schema),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $robots }}">
    <meta name="theme-color" content="#173042">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="en" href="{{ $appUrl.$pagePath }}?lang=en">
    <link rel="alternate" hreflang="fr" href="{{ $appUrl.$pagePath }}?lang=fr">
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
    <link rel="icon" href="{{ $appUrl.'/favicon.ico' }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ $appUrl.'/images/logos/travelconnect1.png' }}" sizes="512x512">
    <link rel="apple-touch-icon" href="{{ $appUrl.'/images/logos/travelconnect1.png' }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="{{ app()->getLocale() === 'fr' ? 'fr_CM' : 'en_CM' }}">
    <meta property="og:site_name" content="TravelConnect">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $socialImage }}">
    @if (! $image)
        <meta property="og:image:width" content="570">
        <meta property="og:image:height" content="217">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <title>{{ $title }}</title>
    <script type="application/ld+json">@json($structuredData)</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-[#fbfaf7] text-[#173042] antialiased {{ auth()->check() ? 'authenticated' : '' }}">
    <div id="page-loader" class="pointer-events-none fixed inset-0 z-[100] flex items-center justify-center bg-[#fbfaf7] opacity-0 transition-opacity duration-300" aria-live="polite" aria-label="{{ __('messages.loading_page') }}" role="status">
        <div class="flex flex-col items-center gap-5 text-center">
            <x-brand-logo class="h-12 w-auto max-w-[80vw] sm:h-16" />
            <div class="flex flex-col items-center gap-3">
                <span class="h-8 w-8 animate-spin rounded-full border-[3px] border-[#dfe8e8] border-t-[#e76f51]" aria-label="{{ __('messages.loading') }}"></span>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.loading') }}</p>
                    <p class="mt-1 text-sm text-[#607985]">{{ __('messages.please_wait') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex-1">
        {{ $slot }}
    </div>
    <x-scroll-to-top />
    <script>
        const loader = document.getElementById('page-loader');

        const hidePageLoader = () => {
            if (!loader) return;
            loader.classList.add('pointer-events-none', 'opacity-0');
        };

        hidePageLoader();
    </script>
</body>
</html>
