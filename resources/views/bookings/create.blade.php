<x-auth-layout title="Book package | TravelConnect">
    <div class="min-h-screen bg-[#f7f8f6] px-4 py-10 text-[#173042] sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">Booking</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">Reserve your trip</h1>
                </div>
                <a href="{{ route('packages.show', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">Back to package</a>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1.1fr_.9fr]">
                <div class="rounded-2xl border border-[#e7eceb] bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">Package</p>
                    <h2 class="mt-2 text-2xl font-bold">{{ $package->title }}</h2>
                    <p class="mt-2 text-sm text-[#607985]">{{ $package->destination }} · {{ $package->duration_days }} days</p>

                    <div class="mt-6 rounded-xl bg-[#f7f8f6] p-4">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Available departures</p>
                        <div class="mt-4 space-y-3">
                            @foreach ($availabilities as $availability)
                                <div class="rounded-xl border border-[#dbe3e5] bg-white p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-semibold">{{ $availability->departure_date->format('F j, Y') }}</p>
                                            <p class="text-xs text-[#607985]">Capacity: {{ $availability->capacity }}</p>
                                        </div>
                                        <span class="rounded-full bg-[#edf4f3] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em] text-[#2f9e7a]">{{ $availability->status->value ?? $availability->status }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-[#e7eceb] bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('bookings.store', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="package_availability_id" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Departure</label>
                            <select id="package_availability_id" name="package_availability_id" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                                <option value="">Select departure</option>
                                @foreach ($availabilities as $availability)
                                    <option value="{{ $availability->id }}" {{ old('package_availability_id') == $availability->id ? 'selected' : '' }}>{{ $availability->departure_date->format('F j, Y') }}</option>
                                @endforeach
                            </select>
                            @error('package_availability_id') <p class="mt-2 text-sm text-[#c9483b]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="traveler_count" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Travelers</label>
                            <input id="traveler_count" type="number" min="1" name="traveler_count" value="{{ old('traveler_count', 1) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            @error('traveler_count') <p class="mt-2 text-sm text-[#c9483b]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="notes" class="block text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Notes</label>
                            <textarea id="notes" name="notes" rows="4" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" placeholder="Optional trip details">{{ old('notes') }}</textarea>
                        </div>

                        <div class="rounded-xl bg-[#fff8e8] p-4">
                            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#c58b27]">From</p>
                            <p class="mt-2 text-2xl font-bold">${{ number_format((float) $unitPrice, 2) }} / traveler</p>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">Request booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-auth-layout>
