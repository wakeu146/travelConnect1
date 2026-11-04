<x-auth-layout title="Booking details | TravelConnect">
    <div class="min-h-screen bg-[#f7f8f6] px-4 py-10 text-[#173042] sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">Booking</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">Booking details</h1>
                </div>
                <a href="{{ route('account.bookings', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.my_bookings') }}</a>
            </div>

            <div class="rounded-2xl border border-[#e7eceb] bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-[#e7eceb] pb-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Reference</p>
                        <h2 class="mt-2 text-3xl font-bold">{{ $booking->booking_reference }}</h2>
                    </div>
                    <span class="rounded-full bg-[#f1f6f4] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em] text-[#173042]">{{ $booking->status->value ?? $booking->status }}</span>
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">{{ $booking->booking_type === 'custom' ? __('messages.custom_requests.request_details') : 'Package' }}</p>
                        <p class="mt-2 text-xl font-bold">{{ $booking->package?->title ?? $booking->customRequest?->title ?? __('messages.travel_package') }}</p>
                        <p class="mt-2 text-sm text-[#607985]">{{ __('messages.destination') }}: {{ $booking->customRequest?->destination ?? $booking->package?->destination ?? __('messages.not_available') }}</p>
                        <p class="mt-2 text-sm text-[#607985]">{{ __('messages.departure') }}: {{ $booking->customRequest?->travel_date?->format('F j, Y') ?? $booking->availability?->departure_date?->format('F j, Y') ?? 'TBD' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Agency</p>
                        <p class="mt-2 text-xl font-bold">{{ $booking->agency?->company_name ?? 'Agency' }}</p>
                        <p class="mt-2 text-sm text-[#607985]">Travelers: {{ $booking->traveler_count }}</p>
                    </div>
                </div>

                <dl class="mt-8 grid gap-5 md:grid-cols-3">
                    <div class="rounded-xl bg-[#f7f8f6] p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Total</dt>
                        <dd class="mt-2 text-xl font-bold">{{ $booking->currency }} {{ number_format((float) $booking->total_amount, 2) }}</dd>
                    </div>
                    <div class="rounded-xl bg-[#f7f8f6] p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Payment</dt>
                        <dd class="mt-2 text-xl font-bold">{{ $booking->payment_status->value ?? $booking->payment_status }}</dd>
                    </div>
                    <div class="rounded-xl bg-[#f7f8f6] p-4">
                        <dt class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Expires</dt>
                        <dd class="mt-2 text-xl font-bold">{{ $booking->expires_at ? $booking->expires_at->format('M d, Y H:i') : 'N/A' }}</dd>
                    </div>
                </dl>

                @if ($booking->notes)
                    <div class="mt-8 rounded-xl bg-[#f7f8f6] p-4">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#607985]">Notes</p>
                        <p class="mt-2 text-sm text-[#173042]">{{ $booking->notes }}</p>
                    </div>
                @endif

                @php $statusValue = $booking->status->value ?? $booking->status; @endphp
                @if (in_array($statusValue, ['pending', 'confirmed'], true))
                    <div class="mt-8">
                        <form method="POST" action="{{ route('bookings.cancel', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">Cancel booking</button>
                        </form>
                    </div>
                @endif

                @if (auth()->user()->isTraveler() && $statusValue === 'completed')
                    @if (session('review_status'))
                        <p class="mt-6 rounded-lg bg-[#e8f5ef] px-4 py-3 text-sm font-semibold text-[#247653]">{{ session('review_status') }}</p>
                    @endif
                    @if ($booking->review)
                        @if ($booking->review->status->value === 'pending')
                            <p class="mt-6 rounded-lg bg-[#fffaf0] px-4 py-3 text-sm font-semibold text-[#b7771b]">{{ __('messages.review_pending_notice') }}</p>
                        @endif
                    @else
                        <section data-booking-review-form class="mt-8 border-t border-[#e7eceb] pt-6">
                            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.share_your_experience') }}</p>
                            <h2 class="mt-2 font-serif text-2xl tracking-tight">{{ __('messages.rate_this_agency') }}</h2>
                            <p class="mt-2 text-sm text-[#607985]">{{ __('messages.choose_a_rating') }}</p>
                            <form action="{{ route('reviews.store', ['slug' => \Illuminate\Support\Str::slug($booking->agency->company_name), 'lang' => app()->getLocale()]) }}" method="POST" class="mt-5 grid gap-4">
                                @csrf
                                <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                                <div class="flex gap-1" role="group" aria-label="{{ __('messages.choose_a_rating') }}">
                                    @for ($rating = 1; $rating <= 5; $rating++)<label><input class="sr-only" type="radio" name="rating" value="{{ $rating }}" required><span class="block cursor-pointer text-3xl text-[#d7e0e1] transition hover:text-[#e76f51]">★</span></label>@endfor
                                </div>
                                @error('rating')<p class="text-sm font-semibold text-[#b42318]">{{ $message }}</p>@enderror
                                <label class="grid gap-2 text-sm font-semibold text-[#173042]" for="booking-review-comment">{{ __('messages.share_your_experience') }}
                                    <textarea id="booking-review-comment" name="comment" rows="5" required minlength="10" maxlength="2000" placeholder="{{ __('messages.review_placeholder') }}" class="w-full rounded-xl border border-[#d7e0e1] bg-white px-4 py-3 text-sm text-[#173042] outline-none focus:border-[#e76f51]">{{ old('comment') }}</textarea>
                                </label>
                                @error('comment')<p class="text-sm font-semibold text-[#b42318]">{{ $message }}</p>@enderror
                                <button type="submit" class="w-fit rounded-xl bg-[#173042] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#26495d]">{{ __('messages.publish_review') }}</button>
                            </form>
                        </section>
                    @endif
                @endif

                @if (auth()->user()->isTraveler())
                    <div class="mt-8 border-t border-[#e7eceb] pt-6">
                        <a href="{{ route('account.issues.create', ['type' => 'booking_dispute', 'booking_id' => $booking->id, 'lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.traveler_issues.report_booking_dispute') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-auth-layout>
