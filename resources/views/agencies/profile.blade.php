<x-agency-shell>
    @php $agency = $agency ?? auth()->user()->agency; @endphp
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
                <div class="agency-panel mb-6 rounded-xl border px-4 py-3 text-sm font-medium shadow-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('agency.settings.update', ['lang' => app()->getLocale()]) }}" enctype="multipart/form-data" class="grid gap-8 lg:grid-cols-[1.2fr_.8fr]">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
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

                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <h2 class="text-lg font-bold">{{ __('messages.destinations') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($countries as $country)
                                <label class="agency-panel-muted flex items-center gap-3 rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm">
                                    <input type="checkbox" name="countries[]" value="{{ $country->id }}" {{ $agency->countries->contains($country->id) ? 'checked' : '' }}>
                                    <span>{{ $country->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <h2 class="text-lg font-bold">{{ __('messages.services') }}</h2>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            @foreach ($services as $service)
                                <label class="agency-panel-muted flex items-center gap-3 rounded-xl border border-[#dbe3e5] px-3 py-2 text-sm">
                                    <input type="checkbox" name="services[]" value="{{ $service->id }}" {{ $agency->services->contains($service->id) ? 'checked' : '' }}>
                                    <span>{{ $service->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <h2 class="text-lg font-bold">{{ __('messages.media') }}</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.upload_logo') }}</label>
                                @if ($agency->logoUrl())
                                    <img id="agency-logo-preview" src="{{ $agency->logoUrl() }}" alt="{{ __('messages.agency_logo_alt', ['name' => $agency->company_name]) }}" class="agency-panel-muted mt-3 h-20 w-20 rounded-xl border object-cover" width="80" height="80">
                                    <label class="agency-text-subtle mt-2 inline-flex items-center gap-2 text-xs"><input type="checkbox" name="remove_logo" value="1">{{ __('messages.remove_current_logo') }}</label>
                                @else
                                    <div id="agency-logo-preview-fallback" class="agency-panel-muted mt-3 flex h-20 w-20 items-center justify-center rounded-xl border text-2xl font-bold">{{ str($agency->company_name)->substr(0, 1)->upper() }}</div>
                                    <img id="agency-logo-preview" alt="{{ __('messages.agency_logo_alt', ['name' => $agency->company_name]) }}" class="mt-3 hidden h-20 w-20 rounded-xl border object-cover" width="80" height="80">
                                @endif
                                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-image-preview-input="#agency-logo-preview" class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.upload_cover') }}</label>
                                @if ($agency->coverImageUrl())
                                    <img id="agency-cover-preview" src="{{ $agency->coverImageUrl() }}" alt="{{ __('messages.upload_cover') }}" class="agency-panel-muted mt-3 h-28 w-full rounded-xl border object-cover" width="640" height="112">
                                    <label class="agency-text-subtle mt-2 inline-flex items-center gap-2 text-xs"><input type="checkbox" name="remove_cover_image" value="1">{{ __('messages.remove_current_cover') }}</label>
                                @else
                                    <div id="agency-cover-preview-fallback" class="agency-panel-muted mt-3 flex h-28 items-center justify-center rounded-xl border border-dashed text-xs agency-text-subtle">{{ __('messages.no_cover_uploaded') }}</div>
                                    <img id="agency-cover-preview" alt="{{ __('messages.upload_cover') }}" class="mt-3 hidden h-28 w-full rounded-xl border object-cover" width="640" height="112">
                                @endif
                                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-image-preview-input="#agency-cover-preview" class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]">
                            </div>
                        </div>
                    </div>

                    <div class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <h2 class="text-lg font-bold">{{ __('messages.verification_documents') }}</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.document_type') }}</label>
                                <select form="agency-document-upload" name="type" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm outline-none focus:border-[#e76f51]">
                                    <option value="license">{{ __('messages.license') }}</option>
                                    <option value="id_proof">{{ __('messages.id_proof') }}</option>
                                    <option value="business_registration">{{ __('messages.business_registration') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-[#173042]">{{ __('messages.choose_document') }}</label>
                                <input form="agency-document-upload" type="file" name="document" class="mt-2 block w-full text-sm text-[#607985] file:mr-4 file:rounded-xl file:border-0 file:bg-[#f1f6f4] file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#173042]" required>
                            </div>
                            <button type="submit" form="agency-document-upload" class="inline-flex items-center justify-center rounded-xl bg-[#173042] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#0f2431]">
                                {{ __('messages.upload_document') }}
                            </button>
                        </div>
                        <div class="mt-6 border-t agency-divider pt-4">
                            <h3 class="text-sm font-bold">{{ __('messages.document_review_status') }}</h3>
                            <div class="mt-3 space-y-2">
                                @forelse ($agency->documents as $document)
                                    @php $documentStatus = $document->status->value ?? $document->status; @endphp
                                    <div class="agency-panel-muted flex items-center justify-between gap-3 rounded-xl border p-3">
                                        <span class="min-w-0"><span class="agency-text-primary block truncate text-sm font-semibold">{{ __('messages.'.$document->type->value) }}</span><span class="agency-text-subtle mt-1 block text-xs">{{ $document->uploaded_at?->translatedFormat('d M Y') }}</span></span>
                                        <x-agency.status-badge :label="__('messages.document_status_'.$documentStatus)" :tone="$documentStatus === 'approved' ? 'success' : ($documentStatus === 'rejected' ? 'danger' : 'warning')" />
                                    </div>
                                @empty
                                    <p class="agency-text-subtle text-sm">{{ __('messages.no_documents') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#e76f51] px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95f42]">
                        {{ __('messages.save_profile') }}
                    </button>
                </div>
            </form>
            <form id="agency-document-upload" method="POST" action="{{ route('agency.documents.store', ['lang' => app()->getLocale()]) }}" enctype="multipart/form-data" class="hidden">@csrf</form>
        </div>
    </div>
</x-agency-shell>
