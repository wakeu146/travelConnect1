@extends('admin.layouts.dashboard')

@section('title', $pageTitle.' | '.__('messages.admin_dashboard'))

@section('dashboard-content')
    <div class="mx-auto grid w-full max-w-6xl gap-8">
        <header class="grid gap-4">
            <nav aria-label="{{ __('messages.admin_nav.breadcrumb') }}">
                <ol class="flex flex-wrap items-center gap-2 text-xs font-semibold">
                    @foreach ($breadcrumbs as $index => $breadcrumb)
                        @if (! $loop->first)
                            <li aria-hidden="true"><x-admin.icon name="chevron_right" class="h-3.5 w-3.5 admin-muted" /></li>
                        @endif
                        <li @if ($loop->last) aria-current="page" class="admin-heading" @else class="admin-muted" @endif>{{ $breadcrumb }}</li>
                    @endforeach
                </ol>
            </nav>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="grid gap-2">
                    <p class="text-xs font-bold uppercase tracking-[.16em] admin-accent">{{ $sectionTitle }}</p>
                    <h1 class="font-serif text-3xl tracking-tight sm:text-4xl admin-heading">{{ $pageTitle }}</h1>
                </div>
                <span class="admin-badge inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold">
                    {{ __('messages.admin_nav.super_admin_workspace') }}
                </span>
            </div>
        </header>

        @if ($activePage === 'overview')
            @include('admin.pages.overview.index')
        @elseif ($activeSection !== 'main' && $activePage === null)
            @include('admin.pages.section-hub.index')
        @elseif ($activePage === 'alerts')
            @include('admin.pages.alerts.index')
        @elseif (in_array($activePage, ['all_agencies', 'pending_verification', 'verified_agencies', 'unverified_agencies', 'suspended_agencies'], true))
            @if ($agencyProfile)
                @include('admin.pages.agencies.show', ['profile' => $agencyProfile])
            @else
                @include('admin.pages.agencies.index', ['directory' => $agencyDirectory])
            @endif
        @elseif ($usersPageData !== null && in_array($activePage, ['all_users', 'travelers', 'agency_owners', 'administrators', 'suspended_deactivated_accounts'], true))
            @if (! empty($usersPageData['userDetails']))
                @include('admin.users.show', $usersPageData)
            @else
                @include('admin.users.users', $usersPageData)
            @endif
        @else
            <section class="admin-empty-state grid justify-items-center gap-4 border p-8 text-center sm:p-12" aria-labelledby="admin-placeholder-title">
                <span class="admin-empty-icon flex h-12 w-12 items-center justify-center rounded-xl">
                    <x-admin.icon name="file" class="h-6 w-6" />
                </span>
                <div class="grid max-w-lg gap-2">
                    <h2 id="admin-placeholder-title" class="text-lg font-bold admin-heading">{{ __('messages.admin_nav.page_in_preparation') }}</h2>
                    <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_nav.page_placeholder_description') }}</p>
                </div>
            </section>
        @endif
    </div>
@endsection
