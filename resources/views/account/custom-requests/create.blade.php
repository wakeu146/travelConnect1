<x-account-shell :title="__('messages.custom_requests.create').' | TravelConnect'">
    <main data-custom-request-page class="mx-auto grid max-w-5xl gap-6 px-5 py-8 text-[#173042] sm:px-8 lg:px-10">
        <header><a class="text-sm font-bold text-[#e76f51]" href="{{ route('account.custom-requests.index', ['lang' => app()->getLocale()]) }}">← {{ __('messages.custom_requests.my_requests') }}</a><h1 class="mt-3 font-serif text-4xl">{{ __('messages.custom_requests.create') }}</h1><p class="mt-2 text-sm text-[#607985]">{{ __('messages.custom_requests.form_intro') }}</p></header>
        @if ($errors->any())<div role="alert" class="grid gap-1 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('account.custom-requests.store', ['lang' => app()->getLocale()]) }}" class="grid gap-6">
            @csrf
            <fieldset class="grid gap-4 rounded-2xl border border-[#e7eceb] bg-white p-5 sm:grid-cols-2">
                <legend class="px-2 text-lg font-bold">{{ __('messages.custom_requests.travel_information') }}</legend>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">{{ __('messages.custom_requests.title') }}<input required name="title" value="{{ old('title') }}" maxlength="191" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.origin') }}<input required name="origin" value="{{ old('origin') }}" maxlength="191" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.destination') }}<input required name="destination" value="{{ old('destination') }}" maxlength="191" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.destination_country') }}<input name="destination_country" value="{{ old('destination_country') }}" maxlength="191" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.departure') }}<input required type="date" name="travel_date" min="{{ today()->toDateString() }}" value="{{ old('travel_date') }}" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.return') }}<input type="date" name="return_date" min="{{ old('travel_date', today()->toDateString()) }}" value="{{ old('return_date') }}" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.traveler_count') }}<input required type="number" min="1" max="100" name="traveler_count" value="{{ old('traveler_count', 1) }}" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.purpose') }}<input name="purpose" value="{{ old('purpose') }}" maxlength="120" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="flexible_dates" value="1" @checked(old('flexible_dates'))>{{ __('messages.custom_requests.flexible_dates') }}</label>
            </fieldset>
            <fieldset class="grid gap-4 rounded-2xl border border-[#e7eceb] bg-white p-5 sm:grid-cols-2">
                <legend class="px-2 text-lg font-bold">{{ __('messages.custom_requests.services_budget') }}</legend>
                <div class="grid gap-2 sm:col-span-2"><p class="text-sm font-semibold">{{ __('messages.custom_requests.required_services') }}</p><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach ($services as $service)<label class="flex items-center gap-2 rounded-lg border border-[#e7eceb] p-3 text-sm"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, old('service_ids', [])))>{{ $service->name }}</label>@endforeach</div></div>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.minimum_budget') }}<input type="number" min="0" step="0.01" name="budget_min" value="{{ old('budget_min') }}" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.maximum_budget') }}<input type="number" min="0" step="0.01" name="budget_max" value="{{ old('budget_max') }}" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.currency') }}<input required name="budget_currency" value="{{ old('budget_currency', config('travelconnect.currency')) }}" minlength="3" maxlength="3" pattern="[A-Z]{3}" class="rounded-lg border border-[#dce5e2] p-3"></label>
                <label class="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" name="budget_flexible" value="1" @checked(old('budget_flexible'))>{{ __('messages.custom_requests.flexible_budget') }}</label>
            </fieldset>
            <fieldset class="grid gap-4 rounded-2xl border border-[#e7eceb] bg-white p-5">
                <legend class="px-2 text-lg font-bold">{{ __('messages.custom_requests.additional_requirements') }}</legend>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.description') }}<textarea required name="description" rows="4" maxlength="10000" class="rounded-lg border border-[#dce5e2] p-3">{{ old('description') }}</textarea></label>
                <label class="grid gap-1 text-sm font-semibold">{{ __('messages.custom_requests.special_requirements') }}<textarea name="special_requirements" rows="3" maxlength="5000" class="rounded-lg border border-[#dce5e2] p-3">{{ old('special_requirements') }}</textarea></label>
            </fieldset>
            <fieldset class="grid gap-4 rounded-2xl border border-[#e7eceb] bg-white p-5">
                <legend class="px-2 text-lg font-bold">{{ __('messages.custom_requests.sharing') }}</legend>
                <label class="flex items-start gap-3 rounded-xl border p-4"><input required type="radio" name="sharing_mode" value="all" @checked(old('sharing_mode') === 'all')><span><strong class="block">{{ __('messages.custom_requests.share_all') }}</strong><span class="text-sm text-[#607985]">{{ __('messages.custom_requests.share_all_hint', ['count' => $eligibleAgencyCount]) }}</span></span></label>
                <label class="flex items-start gap-3 rounded-xl border p-4"><input required type="radio" name="sharing_mode" value="selected" @checked(old('sharing_mode', 'selected') === 'selected')><span><strong class="block">{{ __('messages.custom_requests.select_agencies') }}</strong><span class="text-sm text-[#607985]">{{ __('messages.custom_requests.select_agencies_hint') }}</span></span></label>
                <div class="grid gap-2 sm:grid-cols-2">@foreach ($agencies as $agency)<label class="flex items-start gap-3 rounded-xl border border-[#e7eceb] p-3"><input type="checkbox" name="agency_ids[]" value="{{ $agency->id }}" @checked(in_array($agency->id, old('agency_ids', [])))><span><strong class="block">{{ $agency->company_name }}</strong><span class="text-xs text-[#607985]">{{ __('messages.custom_requests.verified_agency') }} · {{ $agency->services->pluck('name')->join(', ') ?: __('messages.custom_requests.travel_services') }}</span></span></label>@endforeach</div>
                {{ $agencies->links() }}
            </fieldset>
            <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('account.custom-requests.index', ['lang' => app()->getLocale()]) }}" class="rounded-xl border px-5 py-3 text-sm font-bold">{{ __('messages.cancel') }}</a><button type="submit" class="rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white">{{ __('messages.custom_requests.publish') }}</button></div>
        </form>
    </main>
</x-account-shell>
