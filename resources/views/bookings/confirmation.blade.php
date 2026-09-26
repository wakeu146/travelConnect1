<x-auth-layout title="Booking confirmation | TravelConnect">
    <div class="min-h-screen bg-[#f7f8f6] px-4 py-10 text-[#173042] sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl">
            <div class="rounded-2xl border border-[#e7eceb] bg-white p-8 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#edf4f3] text-xl text-[#2f9e7a]">✓</span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">Booking</p>
                        <h1 class="mt-2 font-serif text-4xl tracking-tight">Booking request received</h1>
                    </div>
                </div>

                <div class="mt-8 rounded-xl bg-[#f7f8f6] p-5">
                    <p class="text-sm text-[#607985]">Your request has been submitted successfully.</p>
                    <p class="mt-3 text-2xl font-bold">{{ $booking->booking_reference }}</p>
                    <p class="mt-2 text-sm text-[#607985]">Status: {{ $booking->status->value ?? $booking->status }}</p>
                </div>

                <dl class="mt-8 grid gap-5 md:grid-cols-2">
                    <div class="rounded-xl bg-[#f7f8f6] p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Package</dt>
                        <dd class="mt-2 text-xl font-bold">{{ $booking->package?->title ?? 'Package' }}</dd>
                    </div>
                    <div class="rounded-xl bg-[#f7f8f6] p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Departure</dt>
                        <dd class="mt-2 text-xl font-bold">{{ $booking->availability?->departure_date?->format('F j, Y') ?? 'TBD' }}</dd>
                    </div>
                </dl>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white hover:bg-[#d95f42]">View booking</a>
                    <a href="{{ route('dashboard', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-5 py-3 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">Back to dashboard</a>
                </div>
            </div>
        </div>
    </div>
</x-auth-layout>
