@props(['name', 'image' => null, 'alt', 'description', 'country' => null, 'countries' => [], 'location' => null, 'service' => null, 'rating' => '—', 'reviewCount' => 0, 'trustScore' => null, 'useRealImage' => false, 'saved' => false])
@php($agencyImages = ['Atlas Horizon Voyages' => 'atlas-horizon.jpg', 'Lumiere Routes' => 'lumiere-routes.jpg', 'Northstar Escapes' => 'northstar-escapes.jpg', 'Sirocco Travel House' => 'sirocco-travel.jpg', 'Blue Fern Expeditions' => 'blue-fern.jpg'])
@php($displayCountries = count($countries) ? $countries : ($country ? [$country] : []))
@php($cardImage = $useRealImage ? ($image ?? $agencyImages[$name] ?? null) : ($agencyImages[$name] ?? $image))
@php($cardImageUrl = $cardImage && (str_starts_with($cardImage, 'http') || str_starts_with($cardImage, '/')) ? $cardImage : ($cardImage ? asset('images/agencies/'.$cardImage) : null))
<article class="group relative overflow-hidden border border-[#dbe3e5] bg-white shadow-[0_12px_40px_rgba(23,48,66,.06)] transition hover:-translate-y-1 hover:shadow-[0_16px_45px_rgba(23,48,66,.12)]">
    <div class="relative h-64 overflow-hidden">
        @if ($cardImageUrl)
            <img src="{{ $cardImageUrl }}" alt="{{ $alt }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div data-agency-logo-fallback class="flex h-full items-center justify-center bg-[#edf4f3] font-serif text-5xl text-[#55707c]" aria-label="{{ $name }}">{{ collect(explode(' ', $name))->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->implode('') }}</div>
        @endif
        <span data-verified-badge class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#173042]"><span class="h-2 w-2 rounded-full bg-[#2f9e7a]"></span>{{ __('messages.verified_agency_badge') }}</span>
        <button type="button" data-favorite-toggle data-agency-slug="{{ \Illuminate\Support\Str::slug($name) }}" data-saved="{{ $saved ? 'true' : 'false' }}" data-authenticated="{{ auth()->check() ? 'true' : 'false' }}" data-login-url="{{ route('login', ['lang' => app()->getLocale()]) }}" aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ __('messages.save_agency') }} {{ $name }}" class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-xl {{ $saved ? 'text-[#e76f51]' : 'text-[#173042]' }} transition hover:text-[#e76f51]"><span data-favorite-icon aria-hidden="true">{{ $saved ? '♥' : '♡' }}</span></button>
    </div>
    <a href="{{ route('agency.show', ['slug' => \Illuminate\Support\Str::slug($name)]) }}" class="block p-6 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-[#e76f51]">
        <div class="flex items-start justify-between gap-3"><h3 class="text-xl font-semibold">{{ $name }}</h3>@if ($rating !== '—')<x-rating-stars class="shrink-0" :rating="$rating" :count="$reviewCount" />@endif</div>
        @if ($description)<p class="mt-2 text-sm leading-6 text-[#607985]">{{ $description }}</p>@endif
        @if (filled($location))<p class="mt-3 flex items-center gap-2 text-sm font-semibold text-[#607985]"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span>{{ __('messages.agency_location') }}: {{ $location }}</span></p>@endif
        <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-[#55707c]">@foreach ($displayCountries as $displayCountry)<span class="rounded-full bg-[#edf4f3] px-3 py-1.5">{{ $displayCountry }}</span>@endforeach @if ($service)<span class="rounded-full bg-[#edf4f3] px-3 py-1.5">{{ $service }}</span>@endif</div>
        @if (! is_null($trustScore))<p class="mt-4 text-sm font-semibold text-[#173042]">{{ __('messages.trust_score') }}: {{ $trustScore }}</p>@endif
        <span class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-[#e76f51]">{{ __('messages.view_agency_profile') }} <span aria-hidden="true">&rarr;</span></span>
    </a>
</article>
