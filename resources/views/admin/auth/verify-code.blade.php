@extends('admin.layouts.app')

@section('title', $requiresAuthenticator ? __('messages.admin_totp_verification_title') : __('messages.admin_verification_title'))

@section('content')
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-md border border-[#dbe3e5] bg-white p-7 shadow-sm sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">TravelConnect · {{ $requiresAuthenticator ? __('messages.admin_totp_verification_title') : __('messages.admin_verification_title') }}</p>
            <h1 class="mt-3 font-serif text-3xl">{{ $requiresAuthenticator ? __('messages.admin_totp_verification_title') : __('messages.admin_verification_title') }}</h1>
            <p class="mt-3 text-sm leading-6 text-[#607985]">{{ $requiresAuthenticator ? __('messages.admin_totp_verification_intro') : __('messages.admin_verification_intro') }}</p>
            <p class="mt-2 text-sm font-semibold text-[#173042]">{{ $admin->email }}</p>

            @if (session('status'))
                <p class="mt-5 rounded-lg bg-[#e8f5ef] px-4 py-3 text-sm font-semibold text-[#247653]" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <p class="mt-5 rounded-lg border border-[#fda29b] bg-[#fef3f2] px-4 py-3 text-sm font-semibold text-[#b42318]" role="alert">{{ $errors->first() }}</p>
            @endif

            <form action="{{ route('admin.login.verify.submit', ['lang' => app()->getLocale()]) }}" method="POST" class="mt-6 grid gap-4">
                @csrf
                <label for="code" class="grid gap-2 text-sm font-semibold">
                    {{ __('messages.admin_verification_code') }}
                    <input id="code" name="code" type="text" inputmode="{{ $requiresAuthenticator ? 'text' : 'numeric' }}" autocomplete="one-time-code" placeholder="{{ $requiresAuthenticator ? __('messages.admin_totp_code_placeholder') : __('messages.admin_email_code_placeholder') }}" @unless ($requiresAuthenticator) pattern="[0-9]{6}" @endunless maxlength="{{ $requiresAuthenticator ? 32 : 6 }}" @if ($requiresAuthenticator) aria-describedby="totp-recovery-hint" @endif required class="w-full rounded-xl border border-[#d7e0e1] px-4 py-3 text-center text-xl tracking-[.3em] outline-none focus:border-[#e76f51]" />
                </label>
                <button type="submit" class="rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ $requiresAuthenticator ? __('messages.admin_totp_verification_submit') : __('messages.admin_verification_submit') }}</button>
            </form>

            @if ($requiresAuthenticator)
                <p id="totp-recovery-hint" class="mt-4 border-t border-[#dbe3e5] pt-4 text-sm leading-6 text-[#607985]">{{ __('messages.admin_totp_recovery_hint') }}</p>
            @else
                <form action="{{ route('admin.login.resend', ['lang' => app()->getLocale()]) }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-[#e76f51] hover:text-[#b94d35]">{{ __('messages.admin_resend_code') }}</button>
                </form>
            @endif

            <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="mt-5 block text-center text-sm font-semibold text-[#607985] underline decoration-[#e76f51] underline-offset-4 transition hover:text-[#173042]">
                {{ __('messages.back_to_log_in') }}
            </a>
        </section>
    </main>
@endsection