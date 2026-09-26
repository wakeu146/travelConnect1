<x-agency-shell>
    <div class="mx-auto max-w-3xl">
            <div class="mb-8 flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.availability') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ __('messages.create_departure') }}</h1>
                </div>
                <a href="{{ route('agency.availability.index', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.back') }}</a>
            </div>

            <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                <form method="POST" action="{{ route('agency.availability.store', ['lang' => app()->getLocale()]) }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="package_id" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.package') }}</label>
                        <select id="package_id" name="package_id" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            <option value="">{{ __('messages.select_package') }}</option>
                            @foreach ($packages as $package)
                                <option value="{{ $package->id }}" {{ old('package_id') == $package->id ? 'selected' : '' }}>{{ $package->title }}</option>
                            @endforeach
                        </select>
                        @error('package_id') <p class="mt-2 text-sm text-[#c9483b]">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="departure_date" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.departure_date') }}</label>
                            <input id="departure_date" type="date" name="departure_date" value="{{ old('departure_date') }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            @error('departure_date') <p class="mt-2 text-sm text-[#c9483b]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="capacity" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.capacity') }}</label>
                            <input id="capacity" type="number" min="1" name="capacity" value="{{ old('capacity', 1) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            @error('capacity') <p class="mt-2 text-sm text-[#c9483b]">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="status" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.status') }}</label>
                        <select id="status" name="status" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                            <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>{{ __('messages.cancelled') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ __('messages.notes') }}</label>
                        <textarea id="notes" name="notes" rows="4" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" placeholder="{{ __('messages.optional_travel_notes') }}">{{ old('notes') }}</textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">{{ __('messages.save_departure') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-agency-shell>
