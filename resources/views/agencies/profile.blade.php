<x-auth-layout title="{{ __('messages.agency_profile') }} | TravelConnect">
    @php $agency = $agency ?? auth()->user()->agency; @endphp
    <div class="min-h-screen bg-[#f7f8f6] px-4 py-10 text-[#173042] sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.agency_profile') }}</p>
                    <h1 class="mt-3 font-serif text-4xl tracking-tight">{{ $agency->company_name }}</h1>
                </div>
                <a href="{{ route('agency.dashboard', ['lang' => app()->getLocale()]) }}" class="inline-flex items-center justify-center rounded-xl border border-[#dbe3e5] px-4 py-2.5 text-sm font-semibold text-[#173042] hover:border-[#e76f51] hover:text-[#e76f51]">
                    {{ __('messages.back_to_dashboard') }}
                </a>
            </div>

            @if (session('status'))
                <div class="mb-6 rounded-xl border border-[#dbe3e5] bg-white px-4 py-3 text-sm font-medium text-[#173042] shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('agency.settings.update', ['lang' => app()->getLocale()]) }}" enctype="multipart/form-data" class="grid gap-8 lg:grid-cols-[1.2fr_.8fr]">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.agency_profile') }}</h2>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.company_name') }}</label>
                                <input name="company_name" value="{{ old('company_name', $agency->company_name) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.name') }}</label>
                                <input name="name" value="{{ old('name', $agency->owner->name) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.email') }}</label>
                                <input type="email" name="email" value="{{ old('email', $agency->owner->email) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.phone') }}</label>
                                <input name="phone" value="{{ old('phone', $agency->owner->phone) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.agency_phone') }}</label>
                                <input name="agency_phone" value="{{ old('agency_phone', $agency->phone) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.agency_email') }}</label>
                                <input type="email" name="agency_email" value="{{ old('agency_email', $agency->email) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.description') }}</label>
                                <textarea name="description" rows="5" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">{{ old('description', $agency->description) }}</textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.license_number') }}</label>
                                <input name="license_number" value="{{ old('license_number', $agency->license_number) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.website') }}</label>
                                <input type="url" name="website" value="{{ old('website', $agency->website) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.address') }}</label>
                                <input name="address" value="{{ old('address', $agency->address) }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.destinations') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($countries as $country)
                                <label class="flex items-center gap-3 rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2 text-sm">
                                    <input type="checkbox" name="countries[]" value="{{ $country->id }}" {{ $agency->countries->contains($country->id) ? 'checked' : '' }}>
                                    <span>{{ $country->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.services') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($services as $service)
                                <label class="flex items-center gap-3 rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-3 py-2 text-sm">
                                    <input type="checkbox" name="services[]" value="{{ $service->id }}" {{ $agency->services->contains($service->id) ? 'checked' : '' }}>
                                    <span>{{ $service->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.media') }}</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.upload_logo') }}</label>
                                <input type="file" name="logo" accept="image/*" class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.upload_cover') }}</label>
                                <input type="file" name="cover_image" accept="image/*" class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]">
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[#e7eceb] bg-white p-6">
                        <h2 class="text-lg font-bold">{{ __('messages.verification_documents') }}</h2>
                        <form method="POST" action="{{ route('agency.documents.store', ['lang' => app()->getLocale()]) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.document_type') }}</label>
                                <select name="type" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                                    <option value="license">{{ __('messages.license') }}</option>
                                    <option value="id_proof">{{ __('messages.id_proof') }}</option>
                                    <option value="business_registration">{{ __('messages.business_registration') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.choose_document') }}</label>
                                <input type="file" name="document" class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]" required>
                            </div>
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0f2431]">
                                {{ __('messages.upload_document') }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95f42]">
                        {{ __('messages.save_profile') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-auth-layout>
