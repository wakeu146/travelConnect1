@extends('admin.layouts.app')

@section('title', __('messages.admin_totp_setup_title'))

@section('content')
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg border border-[#dbe3e5] bg-white p-7 shadow-sm sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">TravelConnect · {{ __('messages.admin_totp_setup_title') }}</p>
            <h1 class="mt-3 font-serif text-3xl">{{ __('messages.admin_totp_setup_title') }}</h1>
            <p class="mt-3 text-sm leading-6 text-[#607985]">{{ __('messages.admin_totp_setup_intro') }}</p>

            @if ($errors->any())
                <p class="mt-5 rounded-lg border border-[#fda29b] bg-[#fef3f2] px-4 py-3 text-sm font-semibold text-[#b42318]" role="alert">{{ $errors->first() }}</p>
            @endif

            <div class="mt-6 grid justify-items-center gap-4">
                <img src="{{ $qrCode }}" alt="{{ __('messages.admin_totp_qr_alt') }}" class="h-auto aspect-square w-full max-w-60 border border-[#dbe3e5] bg-white p-2" />
                <div class="grid w-full gap-2 text-center">
                    <p class="text-sm font-semibold">{{ __('messages.admin_totp_manual_entry') }}</p>
                    <code class="select-all break-all rounded border border-[#dbe3e5] bg-[#f7faf9] px-3 py-2 text-sm">{{ $secret }}</code>
                </div>
            </div>

            <form action="{{ route('admin.totp.confirm', ['lang' => app()->getLocale()]) }}" method="POST" class="mt-6 grid gap-4">
                @csrf
                <label for="code" class="grid gap-2 text-sm font-semibold">
                    {{ __('messages.admin_totp_confirm_code') }}
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('messages.admin_totp_code_placeholder') }}" pattern="[0-9]{6}" maxlength="6" required class="w-full rounded-xl border border-[#d7e0e1] px-4 py-3 text-center text-xl tracking-[.3em] outline-none focus:border-[#e76f51]" />
                </label>
                <button type="submit" class="rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.admin_totp_enable') }}</button>
            </form>

            <form action="{{ route('admin.totp.skip', ['lang' => app()->getLocale()]) }}" method="POST" class="mt-4">
                @csrf
                <button type="submit" class="text-sm font-semibold text-[#607985] hover:text-[#173042]">{{ __('messages.admin_totp_skip') }}</button>
            </form>
        </section>
    </main>
@endsection