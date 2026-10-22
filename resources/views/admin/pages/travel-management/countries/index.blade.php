<section class="grid gap-6" data-admin-travel-countries>
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.catalogue_existing_fields') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_travel.country_result_count', ['count' => $countries->total()]) }}</p>
    </header>
    @if (session('status'))<p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'countries', 'lang' => app()->getLocale()]) }}" class="admin-empty-state flex flex-wrap items-end gap-3 border p-4">
        <label for="country-search" class="grid min-w-56 flex-1 gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.search_catalogue') }}<input id="country-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" class="admin-input min-h-10 border px-3 text-sm"></label>
        <button class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.apply_filters') }}</button>
        <a href="{{ route('admin.dashboard.page', ['page' => 'countries', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.clear_filters') }}</a>
    </form>
    <form method="POST" action="{{ route('admin.catalogue.countries.store', ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-[1fr_10rem_auto]">
        @csrf
        <label for="new-country-name" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.country_name') }}<input id="new-country-name" name="name" required maxlength="191" class="admin-input min-h-10 border px-3 text-sm"></label>
        <label for="new-country-code" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.country_code') }}<input id="new-country-code" name="code" required minlength="2" maxlength="2" class="admin-input min-h-10 border px-3 text-sm"></label>
        <button class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.add_country') }}</button>
    </form>
    <div class="grid gap-3">
        @forelse ($countries as $country)
            <form method="POST" action="{{ route('admin.catalogue.countries.update', ['country' => $country->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-[1fr_10rem_auto_auto] sm:items-end">
                @csrf @method('PATCH')
                <label for="country-name-{{ $country->id }}" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.country_name') }}<input id="country-name-{{ $country->id }}" name="name" value="{{ $country->name }}" required maxlength="191" class="admin-input min-h-10 border px-3 text-sm"></label>
                <label for="country-code-{{ $country->id }}" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.country_code') }}<input id="country-code-{{ $country->id }}" name="code" value="{{ $country->code }}" required minlength="2" maxlength="2" class="admin-input min-h-10 border px-3 text-sm"></label>
                <p class="text-xs admin-muted">{{ __('messages.admin_travel.linked_agencies', ['count' => $country->agencies_count]) }}</p>
                <button class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.save_catalogue_entry') }}</button>
            </form>
        @empty
            <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.admin_travel.catalogue_empty') }}</p>
        @endforelse
    </div>
    {{ $countries->links() }}
</section>
