<section class="grid gap-6" data-admin-travel-countries>
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.catalogue_existing_fields') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_travel.country_result_count', ['count' => $countries->total()]) }}</p>
    </header>
    @unless ($countryApiAvailable)
        <p role="alert" class="border border-amber-500/40 bg-amber-500/10 p-4 text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('messages.admin_travel.country_api_unavailable') }}</p>
    @endunless
    @if (session('status'))<p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'countries', 'lang' => app()->getLocale()]) }}" class="admin-empty-state flex flex-wrap items-end gap-3 border p-4">
        <label for="country-search" class="grid min-w-56 flex-1 gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.search_catalogue') }}<input id="country-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" class="admin-input min-h-10 border px-3 text-sm"></label>
        <button class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.apply_filters') }}</button>
        <a href="{{ route('admin.dashboard.page', ['page' => 'countries', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.clear_filters') }}</a>
    </form>
    <form method="POST" action="{{ route('admin.catalogue.countries.store', ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-[1fr_auto]">
        @csrf
        <label for="country-to-add" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.country_name') }}
            <select id="country-to-add" name="country_id" required class="admin-input min-h-10 border px-3 text-sm" @disabled($availableCountries->isEmpty())>
                @forelse ($availableCountries as $country)
                    <option value="{{ $country->id }}">{{ $country->name }} ({{ $country->code }})</option>
                @empty
                    <option value="" selected>{{ __('messages.admin_travel.all_countries_active') }}</option>
                @endforelse
            </select>
        </label>
        <button class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold" @disabled($availableCountries->isEmpty())>{{ __('messages.admin_travel.add_country') }}</button>
    </form>
    <div class="grid gap-3">
        @forelse ($countries as $country)
            <article class="admin-empty-state flex flex-wrap items-center justify-between gap-3 border p-4">
                <div class="grid gap-1">
                    <p class="text-sm font-semibold admin-heading">{{ $country->name }} ({{ $country->code }})</p>
                    <p class="text-xs admin-muted">{{ __('messages.admin_travel.linked_agencies', ['count' => $country->agencies_count]) }}</p>
                </div>
                <form method="POST" action="{{ route('admin.catalogue.countries.destroy', ['country' => $country->id, 'lang' => app()->getLocale()]) }}">
                    @csrf @method('DELETE')
                    <button class="inline-flex min-h-10 items-center border border-rose-500/50 px-4 py-2 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ __('messages.admin_travel.remove_country') }}</button>
                </form>
            </article>
        @empty
            <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.admin_travel.catalogue_empty') }}</p>
        @endforelse
    </div>
    {{ $countries->links() }}
</section>
