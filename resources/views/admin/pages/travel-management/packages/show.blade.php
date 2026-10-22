<section class="grid gap-6" data-admin-package-detail>
    <a href="{{ route('admin.dashboard.page', ['page' => 'all_travel_packages', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-3 py-2 text-sm font-semibold">{{ __('messages.admin_travel.back_to_packages') }}</a>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm font-semibold text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <article class="admin-empty-state grid gap-6 border p-5 sm:p-7">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-2">
                <h2 class="text-2xl font-bold admin-heading">{{ $package->title }}</h2>
                <p class="text-sm admin-muted">{{ $package->agency?->company_name ?? __('messages.not_available') }} · {{ $package->agency?->owner?->name ?? __('messages.not_available') }} · {{ $package->agency?->owner?->email ?? __('messages.not_available') }}</p>
            </div>
            <span class="admin-badge rounded-full px-3 py-1.5 text-xs font-semibold">{{ __('messages.admin_travel.statuses.'.$package->status->value) }}</span>
        </header>

        <dl class="grid gap-4 border-y py-5 admin-border sm:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_travel.destination') }}</dt><dd class="mt-1 text-sm font-semibold admin-heading">{{ $package->destination }}</dd></div>
            <div><dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_travel.price') }}</dt><dd class="mt-1 text-sm font-semibold admin-heading">{{ __('messages.admin_travel.price_from', ['price' => number_format((float) $package->price_from, 2), 'currency' => config('travelconnect.currency')]) }}</dd></div>
            <div><dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_travel.duration') }}</dt><dd class="mt-1 text-sm font-semibold admin-heading">{{ __('messages.admin_travel.days_count', ['count' => $package->duration_days]) }}</dd></div>
            <div><dt class="text-xs font-semibold admin-muted">{{ __('messages.admin_travel.published_at') }}</dt><dd class="mt-1 text-sm font-semibold admin-heading">{{ $package->published_at?->locale(app()->getLocale())->isoFormat('LLL') ?? __('messages.admin_travel.not_published') }}</dd></div>
        </dl>

        <div class="grid gap-2">
            <h3 class="text-base font-bold admin-heading">{{ __('messages.admin_travel.summary') }}</h3>
            <p class="text-sm leading-6 admin-muted">{{ $package->summary }}</p>
            @if ($package->description)
                <p class="text-sm leading-6 admin-muted">{{ $package->description }}</p>
            @endif
        </div>

        @if ($package->images->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($package->images as $image)
                    @if ($image->publicUrl())
                        <figure class="grid gap-2">
                            <img src="{{ $image->publicUrl() }}" alt="{{ $image->caption ?: $package->title }}" class="aspect-video w-full object-cover">
                            @if ($image->caption)<figcaption class="text-xs admin-muted">{{ $image->caption }}</figcaption>@endif
                        </figure>
                    @endif
                @endforeach
            </div>
        @endif

        <section class="grid gap-3" aria-labelledby="package-availability-title">
            <h3 id="package-availability-title" class="text-base font-bold admin-heading">{{ __('messages.admin_travel.availability') }}</h3>
            @forelse ($package->availabilities as $availability)
                <p class="border p-3 text-sm admin-border">{{ $availability->departure_date->locale(app()->getLocale())->isoFormat('LL') }} · {{ __('messages.admin_travel.availability_statuses.'.$availability->status->value) }} · {{ __('messages.admin_travel.capacity', ['count' => $availability->capacity]) }}</p>
            @empty
                <p class="text-sm admin-muted">{{ __('messages.admin_travel.no_availability') }}</p>
            @endforelse
        </section>
    </article>

    @if ($package->status->value !== 'archived')
        <form method="POST" action="{{ route('admin.packages.moderation', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-4 border p-5">
            @csrf
            <h3 class="text-base font-bold admin-heading">{{ __('messages.admin_travel.restrict_package') }}</h3>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.restrict_help') }}</p>
            <input type="hidden" name="decision" value="restrict">
            <label for="package-restriction-reason" class="grid gap-1.5 text-xs font-semibold admin-heading">
                {{ __('messages.admin_travel.moderation_reason') }}
                <textarea id="package-restriction-reason" name="reason" required minlength="10" maxlength="2000" rows="3" class="admin-input w-full border p-3 text-sm">{{ old('reason') }}</textarea>
            </label>
            <button type="submit" class="inline-flex min-h-10 w-fit items-center border border-rose-500/50 px-4 py-2 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ __('messages.admin_travel.restrict_action') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.packages.moderation', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-4 border p-5">
            @csrf
            <h3 class="text-base font-bold admin-heading">{{ __('messages.admin_travel.restore_package') }}</h3>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.restore_help') }}</p>
            <input type="hidden" name="decision" value="restore">
            <label for="package-restore-reason" class="grid gap-1.5 text-xs font-semibold admin-heading">
                {{ __('messages.admin_travel.moderation_reason') }}
                <textarea id="package-restore-reason" name="reason" required minlength="10" maxlength="2000" rows="3" class="admin-input w-full border p-3 text-sm">{{ old('reason') }}</textarea>
            </label>
            <button type="submit" class="admin-nav-link inline-flex min-h-10 w-fit items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_travel.restore_action') }}</button>
        </form>
    @endif

    <section class="grid gap-3" aria-labelledby="package-history-title">
        <h3 id="package-history-title" class="text-base font-bold admin-heading">{{ __('messages.admin_travel.moderation_history') }}</h3>
        @forelse ($history as $event)
            <article class="admin-empty-state grid gap-1 border p-4">
                <p class="text-sm font-semibold admin-heading">{{ __('messages.admin_travel.moderation_actions.'.$event->action) }} · {{ $event->previous_status }} → {{ $event->new_status }}</p>
                <p class="text-sm leading-6 admin-muted">{{ $event->reason }}</p>
                <p class="text-xs admin-muted">{{ $event->actor?->name ?? __('messages.admin_travel.former_admin') }} · {{ $event->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</p>
            </article>
        @empty
            <p class="text-sm admin-muted">{{ __('messages.admin_travel.no_moderation_history') }}</p>
        @endforelse
        {{ $history->links() }}
    </section>
</section>
