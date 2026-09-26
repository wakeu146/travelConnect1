<x-agency-shell>
    @php $package = $package ?? null; @endphp
    <div class="mx-auto max-w-5xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.travel_packages') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ $package ? __('messages.edit_package') : __('messages.create_package') }}</h1>
                </div>
                <a href="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">
                    {{ __('messages.back_to_packages') }}
                </a>
            </div>

            @if (session('status'))
                <div class="agency-panel mb-6 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ $package ? route('agency.packages.update', ['package' => $package->id, 'lang' => app()->getLocale()]) : route('agency.packages.store', ['lang' => app()->getLocale()]) }}" class="space-y-6">
                @csrf
                @if ($package)
                    @method('PUT')
                @endif

                <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.package_title') }}</label>
                            <input name="title" value="{{ old('title', $package?->title ?? '') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.package_summary') }}</label>
                            <input name="summary" value="{{ old('summary', $package?->summary ?? '') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.description') }}</label>
                            <textarea name="description" rows="6" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">{{ old('description', $package?->description ?? '') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.destination') }}</label>
                            <input name="destination" value="{{ old('destination', $package?->destination ?? '') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.duration_days') }}</label>
                            <input type="number" name="duration_days" min="1" max="365" value="{{ old('duration_days', $package?->duration_days ?? 1) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.price_from') }}</label>
                            <input type="number" step="0.01" min="0" name="price_from" value="{{ old('price_from', $package?->price_from ?? 0) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.status') }}</label>
                            <select name="status" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                                <option value="draft" {{ old('status', $package?->status->value ?? 'draft') === 'draft' ? 'selected' : '' }}>{{ __('messages.draft') }}</option>
                                <option value="published" {{ old('status', $package?->status->value ?? 'draft') === 'published' ? 'selected' : '' }}>{{ __('messages.published') }}</option>
                                <option value="archived" {{ old('status', $package?->status->value ?? 'draft') === 'archived' ? 'selected' : '' }}>{{ __('messages.archived') }}</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-start pt-7">
                            <label class="agency-panel-muted flex items-center gap-3 rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm">
                                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $package?->is_featured ? 1 : 0) ? 'checked' : '' }}>
                                <span>{{ __('messages.featured_package') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ $package ? __('messages.save_changes') : __('messages.create_package') }}</button>
                    @if ($package)
                        <button type="submit" form="duplicate-package-form" class="agency-button-secondary inline-flex items-center justify-center rounded-xl border px-5 py-3 text-sm font-semibold">{{ __('messages.duplicate_package') }}</button>
                        <button type="button" onclick='if (confirm(@json(__('messages.delete_package_confirm'))) ) document.getElementById("delete-package-form").submit();' class="agency-button-secondary inline-flex items-center justify-center rounded-xl border px-5 py-3 text-sm font-semibold">{{ __('messages.delete') }}</button>
                    @endif
                </div>
            </form>

            @if ($package)
                <form id="delete-package-form" method="POST" action="{{ route('agency.packages.destroy', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
                <form id="duplicate-package-form" method="POST" action="{{ route('agency.packages.duplicate', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="hidden" onsubmit='return confirm(@json(__('messages.duplicate_package_confirm')) )'>
                    @csrf
                </form>
            @endif

            @if ($package)
                @php
                    $imageReady = $package->images->isNotEmpty();
                    $itineraryReady = $package->itineraryDays->count() >= $package->duration_days;
                    $readinessPercent = (int) round((($imageReady ? 1 : 0) + ($itineraryReady ? 1 : 0)) / 2 * 100);
                @endphp
                <div class="agency-panel mt-8 grid gap-5 rounded-2xl border p-5 shadow-sm sm:grid-cols-[minmax(200px,.6fr)_1.4fr] sm:items-center">
                    <div>
                        <p class="text-sm font-bold">{{ __('messages.package_readiness') }}</p>
                        <p class="agency-text-subtle mt-1 text-xs">{{ __('messages.package_traveler_count', ['count' => (int) $package->travelers_booked]) }} · {{ __('messages.upcoming_departure_count', ['count' => $package->upcoming_departures_count]) }}</p>
                    </div>
                    <div>
                        <x-agency.progress :value="$readinessPercent" :label="__('messages.package_readiness')" />
                        <div class="agency-text-subtle mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs">
                            <span>{{ $imageReady ? '✓' : '○' }} {{ __('messages.package_images_ready') }}</span>
                            <span>{{ $itineraryReady ? '✓' : '○' }} {{ __('messages.package_itinerary_ready') }}</span>
                            <span>{{ __('messages.package_booking_count', ['count' => $package->bookings_count]) }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-10 grid gap-8 lg:grid-cols-2">
                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <h2 class="text-lg font-bold">{{ __('messages.package_images') }}</h2>
                        <form method="POST" action="{{ route('agency.packages.images.store', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.choose_photo') }}</label>
                                <img id="package-image-upload-preview" alt="{{ __('messages.package_image_preview') }}" class="agency-panel-muted mt-3 hidden h-40 w-full rounded-xl border object-cover" width="640" height="160">
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-preview-input="#package-image-upload-preview" required class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.caption') }}</label>
                                <input name="caption" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0f2431]">{{ __('messages.upload_photo') }}</button>
                        </form>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($package->images as $image)
                                <div class="agency-panel-muted overflow-hidden rounded-xl border">
                                    @if ($image->publicUrl())
                                        <img src="{{ $image->publicUrl() }}" alt="{{ $image->caption ?? $package->title }}" class="h-32 w-full object-cover" width="480" height="128" loading="lazy">
                                    @else
                                        <div class="agency-panel-muted flex h-32 items-center justify-center text-xs agency-text-subtle">{{ __('messages.image_unavailable') }}</div>
                                    @endif
                                    <div class="flex items-center justify-between gap-2 p-3">
                                        <span class="agency-text-subtle min-w-0 truncate text-xs">{{ $image->caption ?? __('messages.image') }}</span>
                                        <div class="flex gap-2">
                                            @if ($image->is_primary)
                                                <x-agency.status-badge :label="__('messages.primary')" tone="accent" />
                                            @else
                                                <form method="POST" action="{{ route('agency.packages.images.primary', ['package' => $package->id, 'image' => $image->id, 'lang' => app()->getLocale()]) }}">@csrf<button type="submit" class="agency-button-secondary rounded-lg border px-2 py-1 text-[11px] font-semibold">{{ __('messages.set_primary') }}</button></form>
                                            @endif
                                            <form method="POST" action="{{ route('agency.packages.images.destroy', ['package' => $package->id, 'image' => $image->id, 'lang' => app()->getLocale()]) }}" onsubmit='return confirm(@json(__('messages.delete_image_confirm')) )'>@csrf @method('DELETE')<button type="submit" class="agency-button-secondary rounded-lg border px-2 py-1 text-[11px] font-semibold">{{ __('messages.delete') }}</button></form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <h2 class="text-lg font-bold">{{ __('messages.itinerary') }}</h2>
                        <div class="mt-5 space-y-4">
                            @foreach ($package->itineraryDays as $day)
                                <div class="agency-panel-muted rounded-xl border p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-bold">{{ __('messages.day_number', ['day' => $day->day_number]) }} · {{ $day->title }}</p>
                                            <p class="mt-2 text-sm text-[#607985]">{{ $day->description }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('agency.packages.days.move', ['package' => $package->id, 'day' => $day->id, 'lang' => app()->getLocale()]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#dbe3e5] px-2 py-1 text-[11px] font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]" aria-label="{{ __('messages.move_day_up', ['day' => $day->day_number]) }}">↑</button>
                                            </form>
                                            <form method="POST" action="{{ route('agency.packages.days.move', ['package' => $package->id, 'day' => $day->id, 'lang' => app()->getLocale()]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#dbe3e5] px-2 py-1 text-[11px] font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]" aria-label="{{ __('messages.move_day_down', ['day' => $day->day_number]) }}">↓</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <form method="POST" action="{{ route('agency.packages.days.store', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="mt-6 space-y-3">
                            @csrf
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.day_title') }}</label>
                                <input name="title" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.description') }}</label>
                                <textarea name="description" rows="3" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]"></textarea>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0f2431]">{{ __('messages.add_itinerary_day') }}</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-agency-shell>
