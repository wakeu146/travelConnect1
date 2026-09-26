<x-agency-shell>
    @php
        $user = auth()->user();
        $agency = $agency ?? $user->agency;

        $pendingBookingCount = $pendingBookingCount ?? 0;
        $confirmedBookingCount = $confirmedBookingCount ?? 0;
        $openInquiryCount = $openInquiryCount ?? 0;
        $draftPackageCount = $draftPackageCount ?? 0;
        $publishedPackageCount = $publishedPackageCount ?? 0;
        $incompletePackageCount = $incompletePackageCount ?? 0;
        $bookingStatusCounts = $bookingStatusCounts ?? collect();

        $profileCompletion = collect([
            $agency->company_name,
            $agency->description,
            $agency->email,
            $agency->phone,
            $agency->address,
            $agency->website,
            $agency->logo_path,
            $agency->cover_image_path,
            $agency->countries->count() > 0,
            $agency->services->count() > 0,
        ])->filter(fn ($value) => filled($value) || $value === true)->count();

        $completionPercent = min(100, (int) round(($profileCompletion / 10) * 100));

        $attentionItems = collect();

        if ($pendingBookingCount > 0) {
            $attentionItems->push([
                'title' => __('messages.pending_bookings_need_confirmation'),
                'copy' => trans_choice('messages.pending_booking_action_copy', $pendingBookingCount, ['count' => $pendingBookingCount]),
                'action' => route('agency.bookings.index', ['lang' => app()->getLocale()]),
                'label' => __('messages.view_all'),
            ]);
        }

        if ($draftPackageCount > 0) {
            $attentionItems->push([
                'title' => __('messages.draft_packages_need_review'),
                'copy' => trans_choice('messages.draft_package_action_copy', $draftPackageCount, ['count' => $draftPackageCount]),
                'action' => route('agency.packages.index', ['lang' => app()->getLocale()]),
                'label' => __('messages.manage_travel_packages'),
            ]);
        }

        $nearCapacityDepartures = collect($upcomingDepartures ?? [])->filter(function ($item) {
            return ($item['remaining_capacity'] ?? 0) <= 5;
        })->take(2);

        foreach ($nearCapacityDepartures as $item) {
            $soldOut = ($item['remaining_capacity'] ?? 0) <= 0;
            $attentionItems->push([
                'title' => $soldOut
                    ? __('messages.departure_sold_out_for', ['title' => $item['package']->title ?? __('messages.package')])
                    : __('messages.package_nearly_full', ['title' => $item['package']->title ?? __('messages.package')]),
                'copy' => __('messages.seats_left_for_departure', [
                    'count' => $item['remaining_capacity'] ?? 0,
                    'date' => $item['availability']->departure_date?->format('d M Y') ?? __('messages.next_departure'),
                ]),
                'action' => route('agency.availability.index', ['lang' => app()->getLocale()]),
                'label' => __('messages.manage_departures'),
            ]);
        }

        if ($attentionItems->isEmpty()) {
            $attentionItems->push([
                'title' => __('messages.everything_steady'),
                'copy' => __('messages.no_immediate_actions'),
                'action' => route('agency.dashboard', ['lang' => app()->getLocale()]),
                'label' => __('messages.overview'),
            ]);
        }
    @endphp

    <div class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.agency_workspace') }}</p>
                        <h2 class="mt-3 font-serif text-4xl tracking-tight sm:text-5xl">{{ __('messages.welcome_agency_user', ['name' => $user->name]) }}</h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-[#607985]">{{ __('messages.agency_dashboard_intro') }}</p>
                    </div>
                    <div class="agency-panel w-full max-w-md rounded-2xl border p-5 shadow-sm">
                        <div class="flex items-center justify-between text-sm agency-text-subtle">
                            <span>{{ __('messages.profile_completion') }}</span>
                            <span class="font-bold agency-text-primary">{{ $completionPercent }}%</span>
                        </div>
                        <div class="agency-progress-track mt-3 h-2 overflow-hidden rounded-full">
                            <div class="agency-progress-bar h-full rounded-full" style="width: {{ $completionPercent }}%"></div>
                        </div>
                        <p class="mt-3 text-xs agency-text-subtle">{{ $completionPercent < 100 ? __('messages.agency_profile_completion_hint') : __('messages.agency_profile_complete_hint') }}</p>
                    </div>
                </div>

                <div class="mt-9 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="agency-panel agency-kpi rounded-2xl border p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.travel_packages') }}</p>
                            <span class="agency-icon agency-icon-accent flex h-9 w-9 items-center justify-center rounded-lg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5-8 4.5-8-4.5Zm0 9 8 4.5 8-4.5M4 7.5V16l8 4.5V12"/></svg>
                            </span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $packageCount }}</p>
                        <p class="mt-1 text-xs agency-text-subtle">{{ __('messages.total_packages') }}</p>
                    </div>
                    <div class="agency-panel agency-kpi rounded-2xl border p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.published') }}</p>
                            <span class="agency-icon agency-icon-success flex h-9 w-9 items-center justify-center rounded-lg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="m5 12 4 4L19 2"/></svg>
                            </span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $publishedPackageCount }}</p>
                        <p class="mt-1 text-xs agency-text-subtle">{{ __('messages.live_to_travelers') }}</p>
                    </div>
                    <div class="agency-panel agency-kpi rounded-2xl border p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.pending') }}</p>
                            <span class="agency-icon agency-icon-warning flex h-9 w-9 items-center justify-center rounded-lg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $pendingBookingCount }}</p>
                        <p class="mt-1 text-xs agency-text-subtle">{{ __('messages.needs_confirmation') }}</p>
                    </div>
                    <div class="agency-panel agency-kpi rounded-2xl border p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.upcoming_departures') }}</p>
                            <span class="agency-icon agency-icon-neutral flex h-9 w-9 items-center justify-center rounded-lg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                            </span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ count($upcomingDepartures ?? []) }}</p>
                        <p class="mt-1 text-xs agency-text-subtle">{{ __('messages.scheduled_departures') }}</p>
                    </div>
                    <div class="agency-panel agency-kpi rounded-2xl border p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold">{{ __('messages.open_inquiries') }}</p>
                            <span class="agency-icon agency-icon-accent flex h-9 w-9 items-center justify-center rounded-lg">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-4 2v-6.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8M8 14h5"/></svg>
                            </span>
                        </div>
                        <p class="mt-5 text-3xl font-bold">{{ $openInquiryCount }}</p>
                        <p class="mt-1 text-xs agency-text-subtle">{{ __('messages.inquiries_needing_response') }}</p>
                    </div>
                </div>

                <div class="agency-panel-muted mt-5 flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="agency-text-subtle text-xs font-bold uppercase tracking-[.15em]">{{ __('messages.quick_actions') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('agency.packages.create', ['lang' => app()->getLocale()]) }}" class="agency-button-primary rounded-lg px-3 py-2 text-xs font-bold">{{ __('messages.create_package') }}</a>
                        <a href="{{ route('agency.availability.create', ['lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-bold">{{ __('messages.add_departure') }}</a>
                        <a href="{{ route('agency.bookings.index', ['status' => 'pending', 'lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-bold">{{ __('messages.review_pending_bookings') }}</a>
                        <a href="{{ route('agency.profile', ['lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-bold">{{ __('messages.agency_profile_link') }}</a>
                    </div>
                </div>

                <div class="mt-10 grid gap-8 xl:grid-cols-[1.05fr_.95fr]">
                    <section class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ __('messages.needs_attention') }}</p>
                                <h3 class="mt-2 text-2xl font-bold">{{ __('messages.daily_action_center') }}</h3>
                            </div>
                            <span class="agency-pill agency-pill-accent rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em]">{{ trans_choice('messages.attention_items', $attentionItems->count(), ['count' => $attentionItems->count()]) }}</span>
                        </div>
                        <div class="mt-6 space-y-3">
                            @foreach ($attentionItems as $item)
                                <div class="agency-panel-muted flex items-start justify-between gap-4 rounded-xl border p-4">
                                    <div>
                                        <p class="agency-text-primary text-sm font-bold">{{ $item['title'] }}</p>
                                        <p class="agency-text-subtle mt-1 text-xs leading-5">{{ $item['copy'] }}</p>
                                    </div>
                                    <a href="{{ $item['action'] }}" class="agency-button-secondary shrink-0 rounded-lg border px-3 py-2 text-xs font-bold">{{ $item['label'] }}</a>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ __('messages.business_snapshot') }}</p>
                                <h3 class="mt-2 text-2xl font-bold">{{ __('messages.bookable_inventory') }}</h3>
                            </div>
                            <a href="{{ route('agency.availability.index', ['lang' => app()->getLocale()]) }}" class="agency-accent-text text-sm font-bold">{{ __('messages.view_all') }}</a>
                        </div>
                        <div class="mt-6 space-y-4">
                            @forelse ($upcomingDepartures as $item)
                                @php
                                    $availability = $item['availability'];
                                    $package = $item['package'];
                                    $remaining = (int) ($item['remaining_capacity'] ?? 0);
                                    $capacity = max(1, (int) ($availability->capacity ?? 1));
                                    $used = max(0, $capacity - $remaining);
                                    $occupancy = (int) ($item['capacity_snapshot']['occupancy_percent'] ?? round(($used / $capacity) * 100));
                                    $barWidth = max(0, min(100, $occupancy));
                                @endphp
                                <div class="agency-panel-muted rounded-xl border p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="agency-text-primary text-sm font-bold">{{ $package->title }}</p>
                                            <p class="agency-text-subtle mt-1 text-xs">{{ $availability->departure_date ? $availability->departure_date->translatedFormat('d M Y') : __('messages.next_departure') }}</p>
                                        </div>
                                        <span class="agency-pill agency-pill-neutral rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em]">{{ __('messages.seats_left', ['count' => $remaining]) }}</span>
                                    </div>
                                    <div class="agency-progress-track mt-3 h-2.5 overflow-hidden rounded-full">
                                        <div class="agency-progress-bar h-full rounded-full" style="width: {{ $barWidth }}%"></div>
                                    </div>
                                    <div class="agency-text-subtle mt-3 flex flex-wrap justify-between gap-2 text-xs">
                                        <span>{{ __('messages.reserved_seats_of_capacity', ['reserved' => $used, 'capacity' => $capacity]) }}</span>
                                        <span>{{ __('messages.occupancy') }} {{ $occupancy }}% · {{ __('messages.active_bookings') }} {{ $item['capacity_snapshot']['booking_count'] ?? 0 }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="agency-empty-state rounded-xl border border-dashed p-6 text-center text-sm agency-text-subtle">{{ __('messages.no_upcoming_departures') }}</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="mt-10 grid gap-8 xl:grid-cols-[1.15fr_.85fr]">
                    <section class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ __('messages.recent_bookings') }}</p>
                                <h3 class="mt-2 text-2xl font-bold">{{ __('messages.recent_reservations') }}</h3>
                            </div>
                            <a href="{{ route('agency.bookings.index', ['lang' => app()->getLocale()]) }}" class="agency-accent-text text-sm font-bold">{{ __('messages.view_all') }}</a>
                        </div>

                        <div class="mt-6 space-y-3">
                            @forelse ($recentBookings as $booking)
                                <div class="agency-panel-muted flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="agency-text-primary text-sm font-bold">{{ $booking->booking_reference }}</p>
                                        <p class="agency-text-subtle mt-1 text-xs">{{ $booking->traveler?->name ?? __('messages.traveler') }} · {{ $booking->package?->title ?? __('messages.package') }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        @php $bookingStatus = $booking->status->value ?? $booking->status; @endphp
                                        <span class="agency-pill agency-pill-neutral rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em]">{{ __('messages.booking_status_'.$bookingStatus) }}</span>
                                        <a href="{{ route('agency.bookings.show', ['booking' => $booking->id, 'lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-bold">{{ __('messages.open') }}</a>
                                    </div>
                                </div>
                            @empty
                                <div class="agency-empty-state rounded-xl border border-dashed p-6 text-center text-sm agency-text-subtle">{{ __('messages.no_confirmed_bookings') }}</div>
                            @endforelse
                        </div>
                        <div class="mt-7 border-t agency-divider pt-5">
                            <h4 class="text-sm font-bold">{{ __('messages.booking_status_breakdown') }}</h4>
                            <div class="mt-3 grid grid-cols-2 gap-x-5 gap-y-3">
                                @foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $statusKey)
                                    <div class="flex items-center justify-between gap-2 text-xs">
                                        <span class="agency-text-subtle">{{ __('messages.booking_status_'.$statusKey) }}</span>
                                        <span class="agency-text-primary font-bold tabular-nums">{{ (int) ($bookingStatusCounts[$statusKey] ?? 0) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>

                    <section class="agency-panel rounded-2xl border p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ __('messages.packages') }}</p>
                                <h3 class="mt-2 text-2xl font-bold">{{ __('messages.recent_inventory') }}</h3>
                            </div>
                            <a href="{{ route('agency.packages.index', ['lang' => app()->getLocale()]) }}" class="agency-accent-text text-sm font-bold">{{ __('messages.manage') }}</a>
                        </div>
                        <div class="mt-6 space-y-3">
                            @forelse ($recentPackages as $package)
                                @php $packageStatus = $package->status->value ?? $package->status; @endphp
                                <a href="{{ route('agency.packages.edit', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="agency-panel-muted flex items-center justify-between gap-4 rounded-xl border p-4">
                                    <div>
                                        <p class="agency-text-primary text-sm font-bold">{{ $package->title }}</p>
                                        <p class="agency-text-subtle mt-1 text-xs">{{ $package->destination }} · {{ __('messages.duration_days_label', ['count' => $package->duration_days]) }}</p>
                                    </div>
                                    <span class="agency-pill agency-pill-neutral rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.15em]">{{ __('messages.'.$packageStatus) }}</span>
                                </a>
                            @empty
                                <div class="agency-empty-state rounded-xl border border-dashed p-6 text-center text-sm agency-text-subtle">{{ __('messages.no_packages') }}</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <section class="agency-panel mt-10 rounded-2xl border p-6 shadow-sm">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div><p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ __('messages.package_health') }}</p><h3 class="mt-2 text-xl font-bold">{{ __('messages.packages_need_completion') }}</h3></div>
                        <span class="agency-pill agency-pill-warning rounded-full px-2.5 py-1 text-xs font-bold">{{ $incompletePackageCount }}</span>
                    </div>
                    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @php
                            $packagesNeedingWork = $recentPackages->filter(fn ($package) => $package->images_count === 0 || $package->itinerary_days_count < $package->duration_days)->take(3);
                        @endphp
                        @forelse ($packagesNeedingWork as $package)
                            <a href="{{ route('agency.packages.edit', ['package' => $package->id, 'lang' => app()->getLocale()]) }}" class="agency-panel-muted flex items-center justify-between gap-4 rounded-xl border p-4">
                                <span class="min-w-0"><span class="agency-text-primary block truncate text-sm font-semibold">{{ $package->title }}</span><span class="agency-text-subtle mt-1 block text-xs">{{ $package->images_count }} {{ __('messages.images') }} · {{ $package->itinerary_days_count }}/{{ $package->duration_days }} {{ __('messages.itinerary_days') }}</span></span>
                                <span class="agency-accent-text text-xs font-bold">{{ __('messages.edit') }}</span>
                            </a>
                        @empty
                            <p class="agency-text-subtle text-sm">{{ __('messages.packages_all_ready') }}</p>
                        @endforelse
                    </div>
                </section>

                <section class="agency-panel mt-10 rounded-2xl border p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="agency-accent-text text-xs font-bold uppercase tracking-[.18em]">{{ __('messages.inquiry') }}</p>
                            <h3 class="mt-2 text-2xl font-bold">{{ __('messages.recent_inquiries') }}</h3>
                        </div>
                        <span class="agency-pill agency-pill-neutral rounded-full px-2.5 py-1 text-xs font-bold">{{ count($recentInquiries ?? []) }}</span>
                    </div>
                    <div class="mt-6 divide-y agency-divider">
                        @forelse ($recentInquiries as $inquiry)
                            @php $inquiryStatus = $inquiry->getRawOriginal('status'); @endphp
                            <article class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="agency-text-primary truncate text-sm font-bold">{{ $inquiry->subject }}</p>
                                    <p class="agency-text-subtle mt-1 text-xs">{{ $inquiry->user?->name ?? __('messages.traveler') }} · {{ $inquiry->created_at->translatedFormat('d M Y') }}</p>
                                </div>
                                <span class="agency-pill agency-pill-neutral w-fit rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em]">{{ __('messages.inquiry_status_'.$inquiryStatus) }}</span>
                            </article>
                        @empty
                            <p class="agency-text-subtle py-4 text-sm">{{ __('messages.no_recent_inquiries') }}</p>
                        @endforelse
                    </div>
                </section>
</x-agency-shell>
