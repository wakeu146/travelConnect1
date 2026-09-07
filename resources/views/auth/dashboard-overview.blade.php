<x-auth-layout title="{{ __('messages.dashboard') }} | TravelConnect">
    <main class="flex min-h-screen items-center justify-center bg-[#f7f8f6] px-6 py-12 text-[#173042]">
        <section class="w-full max-w-2xl border border-[#e4e9e7] bg-white p-8 text-center shadow-sm sm:p-12">
            <img src="{{ asset('images/logo.png') }}" alt="TravelConnect logo" class="mx-auto h-16 w-16 object-contain">
            <p class="mt-8 text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.travelconnect') }}</p>
            <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.welcome_back', ['name' => $user->name]) }}</h1>
            <p class="mt-4 text-sm leading-7 text-[#607985]">{{ __('messages.authenticated_session_active', ['name' => $user->name]) }}</p>
            <form action="{{ route('logout') }}" method="POST" class="mt-8">@csrf<button type="submit" class="inline-flex rounded-xl bg-[#173042] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#26495d]">{{ __('messages.logout') }}</button></form>
        </section>
    </main>
</x-auth-layout>
