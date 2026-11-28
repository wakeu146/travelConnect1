<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ asset('images/logos/travelconnect1.png') }}" sizes="512x512">
    <link rel="apple-touch-icon" href="{{ asset('images/logos/travelconnect1.png') }}">
    <title>{{ $title ?? 'Account | TravelConnect' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f1f6f4] text-[#173042] antialiased">
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
    {{ $slot }}
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
