@extends('admin.layouts.app')

@section('title', __('messages.admin_dashboard'))

@section('content')
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-6 py-12">
        <section class="border border-[#dbe3e5] bg-white p-8 shadow-sm sm:p-12">
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.admin_dashboard') }}</p>
            <h1 class="mt-4 font-serif text-3xl">{{ __('messages.admin_dashboard_coming_soon') }}</h1>
            <p class="mt-3 text-sm text-[#607985]">{{ $admin->name }}</p>
            <form action="{{ route('admin.logout', ['lang' => app()->getLocale()]) }}" method="POST" class="mt-8">
                @csrf
                <button type="submit" class="rounded-xl bg-[#173042] px-5 py-3 text-sm font-bold text-white hover:bg-[#26495d]">{{ __('messages.logout') }}</button>
            </form>
        </section>
    </main>
@endsection