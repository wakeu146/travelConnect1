@extends('admin.layouts.app')

@section('title', __('messages.admin_totp_recovery_title'))

@section('content')
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg border border-[#dbe3e5] bg-white p-7 shadow-sm sm:p-9">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">TravelConnect · {{ __('messages.admin_totp_recovery_title') }}</p>
            <h1 class="mt-3 font-serif text-3xl">{{ __('messages.admin_totp_recovery_title') }}</h1>
            <p class="mt-3 text-sm leading-6 text-[#607985]">{{ __('messages.admin_totp_recovery_intro') }}</p>
            <ul class="mt-6 grid grid-cols-2 gap-3 rounded border border-[#dbe3e5] bg-[#f7faf9] p-4 font-mono text-sm">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-sm font-semibold text-[#b42318]">{{ __('messages.admin_totp_recovery_once') }}</p>
            <a href="{{ route('admin.dashboard', ['lang' => app()->getLocale()]) }}" class="mt-6 inline-flex rounded-xl bg-[#173042] px-5 py-3 text-sm font-bold text-white hover:bg-[#26495d]">{{ __('messages.admin_totp_continue') }}</a>
        </section>
    </main>
@endsection