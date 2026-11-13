<section class="grid gap-6" aria-label="{{ __('messages.admin_nav.pages.user_agency_complaints') }}" data-admin-complaints>
    @include('admin.pages.trust-safety.reviews-reports._navigation')

    <div class="flex flex-col gap-2 border-b pb-5 admin-border sm:flex-row sm:items-end sm:justify-between">
        <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.trust_safety.complaints_intro') }}</p>
        <p class="shrink-0 text-xs admin-muted">{{ __('messages.trust_safety.results_count', ['count' => $complaints->total()]) }}</p>
    </div>

    <form method="GET" action="{{ route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2">
        <div class="grid min-w-0 gap-1.5">
            <label for="complaints-search" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.search') }}</label>
            <input id="complaints-search" name="search" value="{{ request('search') }}" type="search" maxlength="120" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.trust_safety.search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="complaints-status" class="text-xs font-semibold admin-heading">{{ __('messages.trust_safety.status') }}</label>
            <select id="complaints-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                <option value="all">{{ __('messages.trust_safety.all_statuses') }}</option>
                @foreach (['open', 'in_review', 'resolved'] as $status)
                    <option value="{{ $status }}" @selected(request('status', 'all') === $status)>{{ __('messages.trust_safety.status_labels.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-2 sm:col-span-2">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.trust_safety.apply_filters') }}
            </button>
            <a href="{{ route('admin.dashboard.page', ['page' => 'user_agency_complaints', 'lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">
                {{ __('messages.trust_safety.clear_filters') }}
            </a>
        </div>
    </form>

    @if ($errors->any())
        <div role="alert" class="border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-800 dark:text-rose-200">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="admin-empty-state overflow-x-auto border">
        <table class="w-full min-w-[68rem] text-left text-sm">
            <thead class="border-b admin-border">
                <tr class="text-xs uppercase tracking-wide admin-muted">
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.case_details') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.traveler') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.related_agency') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.status') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.trust_safety.created') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('messages.admin_users.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y admin-border">
                @forelse ($complaints as $issue)
                    @php($isResolved = $issue->status === 'resolved')
                    <tr data-admin-complaint-id="{{ $issue->id }}">
                        <td class="max-w-64 px-4 py-3">
                            <span class="block truncate font-semibold admin-heading" title="{{ $issue->subject }}">{{ $issue->subject }}</span>
                            <span class="mt-1 block text-xs admin-muted">{{ __('messages.trust_safety.service_type') }}: {{ str($issue->type)->replace('_', ' ')->title() }}</span>
                        </td>
                        <td class="max-w-44 truncate px-4 py-3 admin-heading">{{ $issue->traveler?->name ?? '—' }}</td>
                        <td class="max-w-56 px-4 py-3">
                            <span class="block truncate admin-heading">{{ $issue->booking?->agency?->company_name ?? '—' }}</span>
                            <span class="mt-1 block truncate text-xs admin-muted">{{ $issue->booking?->booking_reference ?? __('messages.trust_safety.no_booking') }}</span>
                        </td>
                        <td class="px-4 py-3"><span class="admin-badge inline-flex border px-2 py-1 text-xs font-semibold">{{ __('messages.trust_safety.status_labels.'.$issue->status) }}</span></td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs admin-muted"><time datetime="{{ $issue->created_at->toIso8601String() }}">{{ $issue->created_at->format('M j, Y · H:i') }}</time></td>
                        <td class="px-4 py-3">
                            <details>
                                <summary class="cursor-pointer font-semibold admin-accent">{{ __('messages.admin_users.view_details') }}</summary>
                                <div class="mt-3 grid min-w-80 gap-4 border p-4 admin-border">
                                    <section class="grid gap-2">
                                        <p class="whitespace-normal text-sm leading-6 admin-heading">{{ $issue->description }}</p>
                                        <p class="text-xs admin-muted">{{ $issue->traveler?->email }}</p>
                                        <p class="text-xs admin-muted">{{ __('messages.trust_safety.related_agency_package') }}: {{ $issue->booking?->agency?->company_name ?? '—' }}@if ($issue->booking?->package) · {{ $issue->booking->package->title }}@endif</p>
                                        <p class="text-xs admin-muted">{{ __('messages.trust_safety.assignee') }}: {{ $issue->assignee?->name ?? __('messages.trust_safety.unassigned') }}</p>
                                    </section>
                                    <section class="grid gap-2 border-t pt-3 admin-border">
                                        <h3 class="text-xs font-semibold admin-muted">{{ __('messages.trust_safety.audit_history') }}</h3>
                                        @forelse ($issue->events as $event)
                                            <p class="whitespace-normal text-xs admin-muted">{{ __('messages.trust_safety.action_labels.'.$event->action) }} · {{ $event->actor?->name ?? __('messages.trust_safety.system_actor') }} · {{ $event->created_at->format('M j, Y · H:i') }}@if ($event->reason) — {{ $event->reason }}@endif</p>
                                        @empty
                                            <p class="text-xs admin-muted">{{ __('messages.trust_safety.no_history') }}</p>
                                        @endforelse
                                    </section>
                                    <form method="POST" action="{{ route('admin.complaints.action', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="grid gap-3 border-t pt-3 admin-border">
                                        @csrf
                                        <label class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.trust_safety.action') }}
                                            <select name="action" required class="admin-input min-h-10 w-full border px-3 text-sm">
                                                @if ($isResolved)
                                                    <option value="reopen">{{ __('messages.trust_safety.reopen') }}</option>
                                                @else
                                                    @foreach (['assign', 'investigate', 'note', 'resolve'] as $action)
                                                        <option value="{{ $action }}" @selected(old('action') === $action)>{{ __('messages.trust_safety.'.$action) }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </label>
                                        @if (! $isResolved)
                                            <label class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.trust_safety.assignee') }}
                                                <select name="assignee_id" class="admin-input min-h-10 w-full border px-3 text-sm">
                                                    <option value="">{{ __('messages.trust_safety.select_admin') }}</option>
                                                    @foreach ($administrators as $administrator)
                                                        <option value="{{ $administrator->id }}" @selected((string) old('assignee_id', $issue->assigned_to) === (string) $administrator->id)>{{ $administrator->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            @error('assignee_id')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                                        @endif
                                        <label for="complaint-reason-{{ $issue->id }}" class="grid gap-1.5 text-xs font-semibold admin-heading">{{ __('messages.custom_requests.reason_optional') }}
                                            <textarea id="complaint-reason-{{ $issue->id }}" name="reason" minlength="3" maxlength="2000" rows="3" class="admin-input w-full border px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                                        </label>
                                        @error('reason')<span class="text-xs text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                                        <button type="submit" class="admin-nav-link inline-flex min-h-9 w-fit items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.trust_safety.save_action') }}</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center admin-muted">{{ __('messages.trust_safety.no_complaints') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $complaints->links() }}
</section>
