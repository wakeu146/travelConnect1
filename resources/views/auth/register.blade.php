<x-auth-layout title="{{ __('messages.create_agency_account') }} | TravelConnect">
    @php($pendingGoogleAgencyRegistration = $pendingGoogleAgencyRegistration ?? null)
    @php($isGoogleAgencySignup = is_array($pendingGoogleAgencyRegistration))
    <main class="grid min-h-screen lg:grid-cols-2">
        <section class="relative hidden overflow-hidden bg-[#173042] lg:block">
            <img src="https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?auto=format&fit=crop&w=1400&q=85" alt="Travel agency destination" class="absolute inset-0 h-full w-full object-cover opacity-60">
            <div class="absolute inset-0 bg-[linear-gradient(145deg,rgba(16,39,54,.95),rgba(23,48,66,.35))]"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white xl:p-16">
                <a href="{{ route('home', ['lang' => app()->getLocale()]) }}" class="flex items-center gap-3 text-lg font-semibold">
                    <x-brand-logo variant="dark" class="h-10 w-auto sm:h-12" />
                    Travel<span class="font-normal text-[#f6c9bb]">Connect</span>
                </a>
                <div class="max-w-sm">
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-[#f6c9bb]">{{ __('messages.for_travel_agencies') }}</p>
                    <h1 class="mt-5 font-serif text-5xl leading-tight">{{ __('messages.grow_your_business') }}</h1>
                    <p class="mt-6 text-base leading-7 text-white/70">{{ __('messages.showcase_your_services') }}</p>
                </div>
                <p class="text-sm text-white/50">{{ __('messages.built_for_agencies') }}</p>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-10">
            <div class="w-full max-w-md">
            <div class="mb-10 flex items-center justify-between lg:hidden">
                    <a href="{{ route('home', ['lang' => app()->getLocale()]) }}" class="flex items-center gap-3 text-lg font-semibold">
                        <x-brand-logo class="h-8 w-auto sm:h-10" />
                        Travel<span class="font-normal text-[#e76f51]">Connect</span>
                    </a>
                    <a href="{{ route('home', ['lang' => app()->getLocale()]) }}" class="text-sm font-semibold text-[#607985]">{{ __('messages.home') }}</a>
                </div>

                <div>
                    <div class="border-b border-[#dbe3e5] pb-7">
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.create_agency_account') }}</p>
                        <h2 class="mt-3 font-serif text-4xl leading-tight tracking-tight">{{ __('messages.join_travelconnect') }}</h2>
                        <p class="mt-4 text-sm leading-6 text-[#607985]">{{ __('messages.build_your_agency_profile') }}</p>
                    </div>

                    @if ($isGoogleAgencySignup)
                        <p class="mt-5 rounded-xl border border-[#b8d7cc] bg-[#f1f8f5] px-4 py-3 text-sm text-[#315b4a]">Google verified your email. Choose an agency name and TravelConnect password to continue.</p>
                    @endif

                    @if ($errors->any())
                        <div class="mt-5 flex items-center gap-3 rounded-xl border border-[#fda29b] bg-[#fef3f2] px-4 py-3 text-sm font-semibold text-[#b42318]" role="alert"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#f04438] text-xs text-white" aria-hidden="true">!</span><span>{{ $errors->first() }}</span></div>
                    @endif

                    <form action="{{ $isGoogleAgencySignup ? route('google.agency.complete') : route('register.agency.store') }}" method="POST" class="mt-8 grid gap-5">
                        @csrf

                        <label class="grid gap-2 text-sm font-semibold" for="agency_name">
                            {{ __('messages.agency_name') }}
                            <span class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M4 20V8l8-4 8 4v12"/>
                                    <path d="M9 20v-6h6v6M9 10h6"/>
                                </svg>
                                <input id="agency_name" type="text" name="agency_name" value="{{ old('agency_name') }}" autocomplete="organization" required placeholder="{{ __('messages.your_agency_name') }}" class="w-full rounded-xl border border-[#d7e0e1] bg-white py-3 pl-12 pr-4 font-normal text-[#173042] outline-none transition placeholder:text-[#9aadb2] focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/15">
                            </span>
                        </label>

                        @if ($isGoogleAgencySignup)
                            <label class="grid gap-2 text-sm font-semibold" for="google_name">
                                {{ __('messages.contact_person') }}
                                <span class="relative">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <circle cx="12" cy="8" r="3"/>
                                        <path d="M5 20c.7-3.2 3-5 7-5s6.3 1.8 7 5"/>
                                    </svg>
                                    <input id="google_name" type="text" value="{{ $pendingGoogleAgencyRegistration['name'] }}" readonly autocomplete="name" class="w-full rounded-xl border border-[#d7e0e1] bg-[#f8faf9] py-3 pl-12 pr-4 font-normal text-[#173042]">
                                </span>
                            </label>

                            <label class="grid gap-2 text-sm font-semibold" for="google_email">
                                {{ __('messages.business_email') }}
                                <span class="relative">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                                        <path d="m3 7 9 6 9-6"/>
                                    </svg>
                                    <input id="google_email" type="email" value="{{ $pendingGoogleAgencyRegistration['email'] }}" readonly autocomplete="email" class="w-full rounded-xl border border-[#d7e0e1] bg-[#f8faf9] py-3 pl-12 pr-4 font-normal text-[#173042]">
                                </span>
                            </label>
                        @else
                            <label class="grid gap-2 text-sm font-semibold" for="name">
                                {{ __('messages.contact_person') }}
                                <span class="relative">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <circle cx="12" cy="8" r="3"/>
                                        <path d="M5 20c.7-3.2 3-5 7-5s6.3 1.8 7 5"/>
                                    </svg>
                                    <input id="name" type="text" name="name" value="{{ old('name') }}" autocomplete="name" required placeholder="{{ __('messages.full_name') }}" class="w-full rounded-xl border border-[#d7e0e1] bg-white py-3 pl-12 pr-4 font-normal text-[#173042] outline-none transition placeholder:text-[#9aadb2] focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/15">
                                </span>
                            </label>

                            <label class="grid gap-2 text-sm font-semibold" for="email">
                                {{ __('messages.business_email') }}
                                <span class="relative">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                                        <path d="m3 7 9 6 9-6"/>
                                    </svg>
                                    <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required placeholder="hello@agency.com" class="w-full rounded-xl border border-[#d7e0e1] bg-white py-3 pl-12 pr-4 font-normal text-[#173042] outline-none transition placeholder:text-[#9aadb2] focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/15">
                                </span>
                            </label>
                        @endif

                        <label class="grid gap-2 text-sm font-semibold" for="password">
                            {{ __('messages.password') }}
                            <span class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <rect x="4" y="10" width="16" height="10" rx="2"/>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                                <input id="password" type="password" name="password" autocomplete="new-password" minlength="6" required placeholder="{{ __('messages.create_a_password') }}" class="w-full rounded-xl border border-[#d7e0e1] bg-white py-3 pl-12 pr-4 font-normal text-[#173042] outline-none transition placeholder:text-[#9aadb2] focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/15">
                            </span>
                        </label>

                        <label class="grid gap-2 text-sm font-semibold" for="password_confirmation">
                            {{ __('messages.confirm_password') }}
                            <span class="relative">
                                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#e76f51]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="m5 12 4 4L19 6"/>
                                </svg>
                                <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required placeholder="{{ __('messages.repeat_your_password') }}" class="w-full rounded-xl border border-[#d7e0e1] bg-white py-3 pl-12 pr-4 font-normal text-[#173042] outline-none transition placeholder:text-[#9aadb2] focus:border-[#e76f51] focus:ring-2 focus:ring-[#e76f51]/15">
                            </span>
                        </label>

                        <label class="flex items-start gap-3 text-sm leading-6 text-[#607985]">
                            <input type="checkbox" name="terms" required class="mt-1 h-4 w-4 rounded border-[#d7e0e1] accent-[#e76f51]">
                            <span>{{ __('messages.i_agree_to_the') }} <a href="#" class="font-semibold text-[#e76f51] hover:text-[#b94d35]">{{ __('messages.terms_of_use') }}</a> {{ __('messages.and') }} <a href="#" class="font-semibold text-[#e76f51] hover:text-[#b94d35]">{{ __('messages.privacy_policy') }}</a>.</span>
                        </label>

                        <button type="submit" data-loading-submit class="flex items-center justify-center gap-2 rounded-xl bg-[#e76f51] px-5 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#d95f42]">
                            <span data-submit-label>{{ __('messages.create_agency_account') }}</span>
                            <span data-submit-spinner class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span>
                        </button>
                    </form>

                    @unless ($isGoogleAgencySignup)
                        <div class="mt-6 flex items-center gap-4 text-xs uppercase tracking-[.15em] text-[#9aadb2]" aria-hidden="true"><span class="h-px flex-1 bg-[#dbe3e5]"></span><span>OR</span><span class="h-px flex-1 bg-[#dbe3e5]"></span></div>
                        <a href="{{ route('google.agency.redirect') }}" class="mt-4 flex items-center justify-center gap-3 rounded-xl border border-[#d7e0e1] bg-white px-5 py-3.5 text-sm font-bold text-[#173042] transition hover:border-[#b8c8cb] hover:bg-[#f8faf9] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e76f51] focus-visible:ring-offset-2">
                            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.7h11c-.5 2.5-2 4.6-4.3 6.1v5h7c4.1-3.8 6.4-9.4 6.4-16.1z"/><path fill="#34A853" d="M24 44c5.9 0 10.9-2 14.5-5.4l-7-5c-1.9 1.3-4.4 2.1-7.5 2.1-5.8 0-10.7-3.9-12.5-9.1h-7.2v5.2C7.9 39 15.3 44 24 44z"/><path fill="#FBBC05" d="M11.5 26.6a12 12 0 0 1 0-7.2v-5.2H4.3a20 20 0 0 0 0 17.6l7.2-5.2z"/><path fill="#EA4335" d="M24 10.3c3.2 0 6 .1 8.2 2.4l6.1-6.1C34.6 3.1 29.9 1 24 1 15.3 1 7.9 6 4.3 14.2l7.2 5.2c1.8-5.2 6.7-9.1 12.5-9.1z"/></svg>
                            <span>Sign up with Google</span>
                        </a>
                    @endunless

                    <p class="mt-8 text-center text-sm text-[#607985]">
                        {{ __('messages.already_have_agency_account') }}
                        <a href="{{ route('login', ['lang' => app()->getLocale()]) }}" class="font-semibold text-[#e76f51] hover:text-[#b94d35]">{{ __('messages.login') }}</a>
                    </p>
                </div>
            </div>
        </section>
    </main>
</x-auth-layout>

