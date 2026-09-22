<x-auth-layout title="{{ $package ? __('messages.edit_package') : __('messages.create_package') }} | TravelConnect">
    @php $package = $package ?? null; @endphp
    <div class="min-h-screen bg-[#f7f8f6] px-4 py-10 text-[#173042] sm:px-6 lg:px-8">
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
                <div class="mb-6 rounded-xl border border-[#dbe3e5] bg-white px-4 py-3 text-sm font-medium text-[#173042] shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ $package ? route('agency.packages.update', ['package' => $package->id, 'lang' => app()->getLocale()]) : route('agency.packages.store', ['lang' => app()->getLocale()]) }}" class="space-y-6">
                @csrf
                @if ($package)
                    @method('PUT')
                @endif

                <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
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
                            <label class="flex items-center gap-3 rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2 text-sm">
                                <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $package?->is_featured ? 1 : 0) ? 'checked' : '' }}>
                                <span>{{ __('messages.featured_package') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ $package ? __('messages.save_changes') : __('messages.create_package') }}</button>
                    @if ($package)
                        <button type="button" onclick="document.getElementById('delete-package-form').submit();" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] bg-white px-5 py-3 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.delete') }}</button>
                    @endif
                </div>
            </form>

            @if ($package)
                <form id="delete-package-form" method="POST" action="{{ route('agency.packages.destroy', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif

            @if ($package)
                <div class="mt-10 grid gap-8 lg:grid-cols-2">
                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.package_images') }}</h2>
                        <form method="POST" action="{{ route('agency.packages.images.store', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.choose_photo') }}</label>
                                <input type="file" name="image" required class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.caption') }}</label>
                                <input name="caption" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0f2431]">{{ __('messages.upload_photo') }}</button>
                        </form>
                        <div class="mt-6 grid gap-3 sm:grid-cols-2">
                            @foreach ($package->images as $image)
                                <div class="overflow-hidden rounded-xl border border-[#e7eceb]">
                                    <img src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->caption ?? $package->title }}" class="h-32 w-full object-cover">
                                    <div class="flex items-center justify-between gap-2 p-3">
                                        <span class="text-xs text-[#607985]">{{ $image->caption ?? __('messages.image') }}</span>
                                        <div class="flex gap-2">
                                            <form method="POST" action="{{ route('agency.packages.images.primary', ['package' => $package->id, 'image' => $image->id, 'lang' => app()->getLocale()]) }}">@csrf<button type="submit" class="text-xs font-semibold text-[#173042]">{{ __('messages.primary') }}</button></form>
                                            <form method="POST" action="{{ route('agency.packages.images.destroy', ['package' => $package->id, 'image' => $image->id, 'lang' => app()->getLocale()]) }}">@csrf @method('DELETE')<button type="submit" class="text-xs font-semibold text-[#e76f51]">{{ __('messages.delete') }}</button></form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.itinerary') }}</h2>
                        <div class="mt-5 space-y-4">
                            @foreach ($package->itineraryDays as $day)
                                <div class="rounded-xl border border-[#e7eceb] p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-bold">Day {{ $day->day_number }} · {{ $day->title }}</p>
                                            <p class="mt-2 text-sm text-[#607985]">{{ $day->description }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('agency.packages.days.move', ['package' => $package->id, 'day' => $day->id, 'lang' => app()->getLocale()]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#dbe3e5] px-2 py-1 text-[11px] font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]" aria-label="Move day {{ $day->day_number }} up">↑</button>
                                            </form>
                                            <form method="POST" action="{{ route('agency.packages.days.move', ['package' => $package->id, 'day' => $day->id, 'lang' => app()->getLocale()]) }}">
                                                @csrf
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-[#dbe3e5] px-2 py-1 text-[11px] font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]" aria-label="Move day {{ $day->day_number }} down">↓</button>
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
</x-auth-layout>
