<section class="grid gap-6" data-admin-travel-services>
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.catalogue_existing_fields') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_travel.service_result_count', ['count' => $services->total()]) }}</p>
    </header>
    @if (session('status'))<p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'travel_services', 'lang' => app()->getLocale()]) }}" class="admin-empty-state flex flex-wrap items-end gap-3 border p-4">
        <label for="service-search" class="grid min-w-56 flex-1 gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.search_catalogue') }}<input id="service-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" class="admin-input min-h-10 border px-3 text-sm"></label>
        <button class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.apply_filters') }}</button>
        <a href="{{ route('admin.dashboard.page', ['page' => 'travel_services', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.clear_filters') }}</a>
    </form>
    <form method="POST" action="{{ route('admin.catalogue.services.store', ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-[1fr_auto]">
        @csrf
        <label for="new-service-name" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.service_name') }}<input id="new-service-name" name="name" required maxlength="191" class="admin-input min-h-10 border px-3 text-sm"></label>
        <button class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.add_service') }}</button>
    </form>
    <div class="grid gap-3">
        @forelse ($services as $service)
            <form method="POST" action="{{ route('admin.catalogue.services.update', ['service' => $service->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                @csrf @method('PATCH')
                <label for="service-name-{{ $service->id }}" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.admin_travel.service_name') }}<input id="service-name-{{ $service->id }}" name="name" value="{{ $service->name }}" required maxlength="191" class="admin-input min-h-10 border px-3 text-sm"></label>
                <p class="text-xs admin-muted">{{ __('messages.admin_travel.linked_agencies', ['count' => $service->agencies_count]) }}</p>
                <button class="admin-nav-link inline-flex min-h-10 items-center justify-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.save_catalogue_entry') }}</button>
            </form>
        @empty
            <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.admin_travel.catalogue_empty') }}</p>
        @endforelse
    </div>
    {{ $services->links() }}
</section>
