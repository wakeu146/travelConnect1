<x-account-shell :title="__('messages.traveler_issues.new_report')">
    <main class="mx-auto grid max-w-7xl gap-8 px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <header class="grid gap-3">
            <a href="{{ route('account.issues.index', ['lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold text-[#607985] underline underline-offset-4 transition hover:text-[#e76f51]">{{ __('messages.traveler_issues.back_to_reports') }}</a>
            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.traveler_issues.title') }}</p>
                <h1 class="font-serif text-3xl tracking-tight sm:text-4xl">{{ __('messages.traveler_issues.new_report') }}</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#607985]">{{ __('messages.traveler_issues.form_intro') }}</p>
            </div>
        </header>

        <div class="grid w-full max-w-4xl justify-self-center gap-4">
            @if ($errors->any())
                <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-[#173042]">
                    <p class="font-bold">{{ __('messages.traveler_issues.form_error_title') }}</p>
                    <p class="mt-1">{{ __('messages.traveler_issues.form_error_description') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('account.issues.store', ['lang' => app()->getLocale()]) }}" class="dashboard-panel grid gap-6 border border-[#e7eceb] bg-white p-5 sm:p-8">
                @csrf
                <div class="grid gap-2">
                    <label for="issue-type" class="text-sm font-bold">{{ __('messages.traveler_issues.type') }}</label>
                    <select id="issue-type" name="type" required class="min-h-11 w-full border border-[#d7e0e1] bg-white px-3 text-sm text-[#173042] outline-none transition focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/20">
                        @foreach (['complaint', 'booking_dispute'] as $type)
                            <option value="{{ $type }}" @selected(old('type', request('type', 'complaint')) === $type)>{{ __('messages.traveler_issues.types.'.$type) }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-2">
                    <label for="issue-booking" class="text-sm font-bold">{{ __('messages.traveler_issues.booking_optional') }}</label>
                    <select id="issue-booking" name="booking_id" class="min-h-11 w-full border border-[#d7e0e1] bg-white px-3 text-sm text-[#173042] outline-none transition focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/20">
                        <option value="">{{ __('messages.traveler_issues.no_booking') }}</option>
                        @foreach ($bookings as $booking)
                            <option value="{{ $booking->id }}" @selected((string) old('booking_id', request('booking_id')) === (string) $booking->id)>{{ $booking->booking_reference }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs leading-5 text-[#607985]">{{ __('messages.traveler_issues.booking_hint') }}</p>
                    @error('booking_id')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-2">
                    <label for="issue-subject" class="text-sm font-bold">{{ __('messages.traveler_issues.subject') }}</label>
                    <input id="issue-subject" name="subject" type="text" minlength="5" maxlength="160" required value="{{ old('subject') }}" class="min-h-11 w-full border border-[#d7e0e1] bg-white px-3 text-sm text-[#173042] outline-none transition focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/20">
                    @error('subject')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-2">
                    <label for="issue-description" class="text-sm font-bold">{{ __('messages.traveler_issues.description') }}</label>
                    <textarea id="issue-description" name="description" rows="7" minlength="10" maxlength="5000" required class="w-full resize-y border border-[#d7e0e1] bg-white px-3 py-2.5 text-sm leading-6 text-[#173042] outline-none transition focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/20">{{ old('description') }}</textarea>
                    @error('description')<p class="text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col-reverse gap-3 border-t border-[#e7eceb] pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('account.issues.index', ['lang' => app()->getLocale()]) }}" class="inline-flex min-h-11 items-center justify-center border border-[#dbe3e5] px-5 py-2 text-sm font-semibold text-[#607985] transition hover:border-[#e76f51] hover:text-[#e76f51]">{{ __('messages.traveler_issues.back_to_reports') }}</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center bg-[#e76f51] px-5 py-2 text-sm font-bold text-white transition hover:bg-[#d95f42]">{{ __('messages.traveler_issues.submit') }}</button>
                </div>
            </form>
        </div>
    </main>
</x-account-shell>
