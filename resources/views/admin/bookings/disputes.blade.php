<section class="grid min-w-0 gap-6" data-admin-booking-disputes>
    <header class="grid gap-3 sm:flex sm:items-end sm:justify-between">
        <div class="grid gap-2">
            <a href="{{ route('admin.bookings.index', ['lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold underline">{{ __('messages.admin_bookings.back_to_list') }}</a>
            <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_disputes.description') }}</p>
        </div>
        <p class="text-xs admin-muted">{{ __('messages.admin_disputes.results', ['from' => $issues->firstItem() ?? 0, 'to' => $issues->lastItem() ?? 0, 'total' => $issues->total()]) }}</p>
    </header>

    @if (session('status'))
        <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('messages.admin_disputes.case_counts') }}">
        @foreach (['open', 'in_review', 'awaiting_information', 'resolved', 'dismissed'] as $status)
            <a href="{{ route('admin.booking-disputes.index', ['lang' => app()->getLocale(), 'status' => $status]) }}" class="admin-empty-state grid gap-1 border p-4">
                <span class="text-xs admin-muted">{{ __('messages.admin_disputes.statuses.'.$status) }}</span>
                <span class="text-2xl font-bold admin-heading">{{ $statusCounts[$status] ?? 0 }}</span>
            </a>
        @endforeach
    </section>

    <form method="GET" action="{{ route('admin.booking-disputes.index', ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="grid min-w-0 gap-1.5 sm:col-span-2">
            <label for="dispute-search" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.search') }}</label>
            <input id="dispute-search" name="search" type="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" class="admin-input min-h-10 w-full border px-3 text-sm" placeholder="{{ __('messages.admin_disputes.search_placeholder') }}">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="dispute-status" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.status') }}</label>
            <select id="dispute-status" name="status" class="admin-input min-h-10 w-full border px-3 text-sm">
                <option value="">{{ __('messages.admin_disputes.all_statuses') }}</option>
                @foreach (['open', 'in_review', 'awaiting_information', 'resolved', 'dismissed'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('messages.admin_disputes.statuses.'.$status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="dispute-priority" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.priority') }}</label>
            <select id="dispute-priority" name="priority" class="admin-input min-h-10 w-full border px-3 text-sm">
                <option value="">{{ __('messages.admin_disputes.all_priorities') }}</option>
                @foreach (['low', 'normal', 'high', 'urgent'] as $priority)
                    <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ __('messages.admin_disputes.priority_labels.'.$priority) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="dispute-category" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.category') }}</label>
            <select id="dispute-category" name="category" class="admin-input min-h-10 w-full border px-3 text-sm">
                <option value="">{{ __('messages.admin_disputes.all_categories') }}</option>
                @foreach (['general', 'booking', 'payment', 'departure', 'service', 'other'] as $category)
                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ __('messages.admin_disputes.categories.'.$category) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="dispute-assignee" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.assignee') }}</label>
            <select id="dispute-assignee" name="assigned_admin_id" class="admin-input min-h-10 w-full border px-3 text-sm">
                <option value="">{{ __('messages.admin_disputes.all_assignees') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) ($filters['assigned_admin_id'] ?? '') === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="dispute-from" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.opened_from') }}</label>
            <input id="dispute-from" name="created_from" type="date" value="{{ $filters['created_from'] ?? '' }}" class="admin-input min-h-10 w-full border px-3 text-sm">
        </div>
        <div class="grid min-w-0 gap-1.5">
            <label for="dispute-to" class="text-xs font-semibold admin-heading">{{ __('messages.admin_disputes.opened_to') }}</label>
            <input id="dispute-to" name="created_to" type="date" value="{{ $filters['created_to'] ?? '' }}" class="admin-input min-h-10 w-full border px-3 text-sm">
        </div>
        @if ($errors->any())
            <div role="alert" class="grid gap-1 text-sm font-semibold text-rose-800 dark:text-rose-200 sm:col-span-2 xl:col-span-4">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_disputes.apply_filters') }}</button>
            <a href="{{ route('admin.booking-disputes.index', ['lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_disputes.clear_filters') }}</a>
        </div>
    </form>

    <div class="admin-empty-state overflow-x-auto border">
        <table class="w-full min-w-[850px] border-collapse text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <tr>
                    <th class="p-3">{{ __('messages.admin_disputes.case') }}</th>
                    <th class="p-3">{{ __('messages.admin_bookings.reference') }}</th>
                    <th class="p-3">{{ __('messages.admin_bookings.traveler') }}</th>
                    <th class="p-3">{{ __('messages.admin_bookings.agency') }}</th>
                    <th class="p-3">{{ __('messages.admin_disputes.category') }}</th>
                    <th class="p-3">{{ __('messages.admin_disputes.priority') }}</th>
                    <th class="p-3">{{ __('messages.admin_disputes.status') }}</th>
                    <th class="p-3">{{ __('messages.admin_disputes.assignee') }}</th>
                    <th class="p-3">{{ __('messages.admin_disputes.opened') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($issues as $issue)
                    <tr class="border-t admin-border">
                        <td class="p-3">
                            <a class="font-semibold underline" href="{{ route('admin.booking-disputes.show', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}">#{{ $issue->id }} · {{ $issue->subject }}</a>
                        </td>
                        <td class="p-3">
                            <a class="font-semibold underline" href="{{ route('admin.bookings.show', ['booking' => $issue->booking->id, 'lang' => app()->getLocale()]) }}">{{ $issue->booking->booking_reference }}</a>
                        </td>
                        <td class="p-3">
                            <span class="block admin-heading">{{ $issue->booking->traveler?->name ?? __('messages.admin_bookings.unavailable') }}</span>
                            <span class="block text-xs admin-muted">{{ $issue->booking->traveler?->email ?? '' }}</span>
                        </td>
                        <td class="p-3 admin-heading">{{ $issue->booking->agency?->company_name ?? __('messages.admin_bookings.unavailable') }}</td>
                        <td class="p-3">{{ __('messages.admin_disputes.categories.'.$issue->category) }}</td>
                        <td class="p-3">{{ __('messages.admin_disputes.priority_labels.'.$issue->priority) }}</td>
                        <td class="p-3">{{ __('messages.admin_disputes.statuses.'.$issue->status) }}</td>
                        <td class="p-3 admin-muted">{{ $issue->assignedAdmin?->name ?? __('messages.admin_disputes.unassigned') }}</td>
                        <td class="p-3 whitespace-nowrap admin-muted">{{ $issue->created_at?->locale(app()->getLocale())->isoFormat('LL') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center admin-muted">{{ __('messages.admin_disputes.no_cases') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $issues->links() }}
</section>
