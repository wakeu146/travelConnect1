<section class="grid gap-6" data-admin-users>
    @php
        $filters = $filters ?? [];
        $isFiltering = collect($filters)->contains(fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="grid gap-2">
        <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.admin_users.description') }}</p>
    </div>

    @if (session('status'))
        <p role="status" class="admin-empty-state border p-4 text-sm font-semibold">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p role="alert" class="admin-empty-state border border-red-300 p-4 text-sm font-semibold text-red-700">{{ session('error') }}</p>
    @endif

    @if ($summary)
        <section class="grid gap-3 sm:grid-cols-3" aria-label="{{ __('messages.admin_users.account_summary') }}">
            @foreach ([
                ['label' => 'total_accounts', 'count' => $summary['total'], 'url' => route('admin.users.index', ['lang' => app()->getLocale()])],
                ['label' => 'active_accounts', 'count' => $summary['active'], 'url' => route('admin.users.index', ['lang' => app()->getLocale(), 'status' => 'active'])],
                ['label' => 'deactivated_accounts', 'count' => $summary['deactivated'], 'url' => route('admin.users.index', ['lang' => app()->getLocale(), 'status' => 'deactivated'])],
            ] as $summaryItem)
                <a href="{{ $summaryItem['url'] }}" class="admin-empty-state grid gap-1 border p-4 focus-visible:outline-2 focus-visible:outline-offset-2">
                    <span class="text-sm admin-muted">{{ __('messages.admin_users.'.$summaryItem['label']) }}</span>
                    <span class="text-2xl font-bold admin-heading">{{ $summaryItem['count'] }}</span>
                </a>
            @endforeach
        </section>
    @endif

    <form method="GET" action="{{ route($currentRoute, ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="grid gap-1.5 sm:col-span-2 xl:col-span-2">
            <label for="users-search" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.search') }}</label>
            <input id="users-search" name="search" type="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" class="admin-input min-h-10 w-full rounded-lg border px-3 text-sm" placeholder="{{ __('messages.admin_users.search_placeholder') }}">
        </div>

        @if (! $lockedRole && ! $deactivatedOnly)
            <div class="grid gap-1.5">
                <label for="users-role" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.role') }}</label>
                <select id="users-role" name="role" class="admin-input min-h-10 w-full rounded-lg border px-3 text-sm">
                    <option value="">{{ __('messages.admin_users.all_roles') }}</option>
                    @foreach ($roleOptions as $roleOption)
                        <option value="{{ $roleOption->value }}" @selected(($filters['role'] ?? '') === $roleOption->value)>{{ __('messages.admin_users.roles.'.$roleOption->value) }}</option>
                    @endforeach
                </select>
            </div>
        @elseif ($lockedRole)
            <div class="grid gap-1.5">
                <span class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.role') }}</span>
                <span class="admin-input flex min-h-10 items-center rounded-lg border px-3 text-sm">{{ __('messages.admin_users.roles.'.$lockedRole->value) }}</span>
            </div>
        @endif

        @if (! $deactivatedOnly)
            <div class="grid gap-1.5">
                <label for="users-status" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.status') }}</label>
                <select id="users-status" name="status" class="admin-input min-h-10 w-full rounded-lg border px-3 text-sm">
                    <option value="">{{ __('messages.admin_users.all_statuses') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('messages.admin_users.statuses.active') }}</option>
                    <option value="deactivated" @selected(($filters['status'] ?? '') === 'deactivated')>{{ __('messages.admin_users.statuses.deactivated') }}</option>
                </select>
            </div>
        @endif

        <div class="grid gap-1.5">
            <label for="users-from" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.registered_from') }}</label>
            <input id="users-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="admin-input min-h-10 w-full rounded-lg border px-3 text-sm">
        </div>

        <div class="grid gap-1.5">
            <label for="users-to" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.registered_to') }}</label>
            <input id="users-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="admin-input min-h-10 w-full rounded-lg border px-3 text-sm">
        </div>

        @if ($errors->any())
            <div class="text-sm font-semibold text-red-700 sm:col-span-2 xl:col-span-6" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-6">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.apply_filters') }}</button>
            <a href="{{ route('admin.users.export', ['lang' => app()->getLocale(), 'list' => $page, ...$filters]) }}" class="admin-nav-link inline-flex min-h-10 items-center justify-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.export_csv') }}</a>
            @if ($isFiltering)
                <a href="{{ route($currentRoute, ['lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.clear_filters') }}</a>
            @endif
            @if (($filters['search'] ?? '') !== '')
                <p class="text-sm admin-muted" aria-live="polite">{{ __('messages.admin_users.searching_for', ['term' => $filters['search']]) }}</p>
            @endif
        </div>
    </form>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm admin-muted" aria-live="polite">
            {{ __('messages.admin_users.results', ['from' => $users->firstItem() ?? 0, 'to' => $users->lastItem() ?? 0, 'total' => $users->total()]) }}
        </p>
        @if ($deactivatedOnly)
            <p class="text-xs admin-muted">{{ __('messages.admin_users.deactivation_note') }}</p>
        @endif
    </div>

    @if ($users->isEmpty())
        <div class="admin-empty-state grid justify-items-center gap-2 border p-8 text-center" data-users-empty>
            <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_users.empty_title') }}</h2>
            <p class="max-w-xl text-sm leading-6 admin-muted">{{ __('messages.admin_users.empty_description') }}</p>
        </div>
    @else
        @if (! $lockedRole || $lockedRole !== \App\Enums\UserRole::ADMIN)
            <form id="admin-users-bulk" method="POST" action="{{ route('admin.users.bulk', ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid gap-3 border p-4 sm:grid-cols-2 xl:grid-cols-4" onsubmit="return confirm('{{ __('messages.admin_users.confirm_bulk') }}')">
                @csrf
                <input type="hidden" name="list" value="{{ $page }}">
                <input type="hidden" name="list_page" value="{{ $currentPage }}">
                @foreach ($filters as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach
                <div class="grid gap-1.5">
                    <label for="bulk-operation" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.bulk_action') }}</label>
                    <select id="bulk-operation" name="operation" required class="admin-input min-h-10 rounded-lg border px-3 text-sm">
                        <option value="deactivate">{{ __('messages.admin_users.deactivate_selected') }}</option>
                        <option value="restore">{{ __('messages.admin_users.restore_selected') }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <label for="bulk-reason" class="text-xs font-semibold admin-muted">{{ __('messages.admin_users.action_reason') }}</label>
                    <input id="bulk-reason" name="reason" required minlength="5" maxlength="1000" class="admin-input min-h-10 rounded-lg border px-3 text-sm" autocomplete="off">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center rounded-lg border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.apply_to_selected') }}</button>
                </div>
                <p class="text-xs leading-5 admin-muted sm:col-span-2 xl:col-span-4">{{ __('messages.admin_users.bulk_selection_hint') }}</p>
            </form>
        @endif

        <div class="admin-empty-state overflow-x-auto border">
            <table class="w-full min-w-[900px] border-collapse text-left text-sm">
                <thead class="admin-muted">
                    <tr class="border-b admin-border">
                        @if (! $lockedRole || $lockedRole !== \App\Enums\UserRole::ADMIN)
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.select') }}</th>
                        @endif
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.name') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.email') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.role') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.status') }}</th>
                        @if ($agencyOwnersPage)
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.agency') }}</th>
                        @endif
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.registered') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">{{ __('messages.admin_users.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-b transition hover:bg-[var(--admin-surface-soft)] focus-within:bg-[var(--admin-surface-soft)] admin-border">
                            @if (! $lockedRole || $lockedRole !== \App\Enums\UserRole::ADMIN)
                                <td class="px-4 py-3">
                                    @if ($user->role !== \App\Enums\UserRole::ADMIN)
                                        <input form="admin-users-bulk" type="checkbox" name="user_ids[]" value="{{ $user->id }}" aria-label="{{ __('messages.admin_users.select_user', ['name' => $user->name]) }}" class="h-4 w-4 rounded border">
                                    @else
                                        <span class="text-xs admin-muted">{{ __('messages.admin_users.protected') }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 font-semibold admin-heading">{{ $user->name }}</td>
                            <td class="px-4 py-3 admin-muted">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="admin-badge inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">{{ __('messages.admin_users.roles.'.$user->role->value) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="admin-badge inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">{{ $user->deleted_at ? __('messages.admin_users.statuses.deactivated') : __('messages.admin_users.statuses.active') }}</span>
                            </td>
                            @if ($agencyOwnersPage)
                                <td class="px-4 py-3 admin-muted">
                                    @if ($user->agency)
                                        <span class="font-semibold admin-heading">{{ $user->agency->company_name }}</span>
                                        <span class="block text-xs">{{ __('messages.admin_users.verification.'.$user->agency->verification_status->value) }} · {{ $user->agency->deleted_at ? __('messages.admin_users.agency_statuses.deactivated') : __('messages.admin_users.agency_statuses.active') }}</span>
                                    @else
                                        {{ __('messages.admin_users.unavailable') }}
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3 admin-muted">{{ $user->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.users.show', ['userId' => $user->id, 'lang' => app()->getLocale(), 'return_page' => $page, 'list_page' => $currentPage, ...$filters]) }}" class="admin-nav-link inline-flex min-h-9 items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.view_details') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <nav aria-label="{{ __('messages.admin_users.pagination') }}" class="admin-users-pagination">
            {{ $users->links() }}
        </nav>
    @endif
</section>
