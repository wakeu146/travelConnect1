<section class="grid gap-6" data-admin-users>
    @php
        $filters = $filters ?? [];
        $userPages = [
            'all_users',
            'travelers',
            'agency_owners',
            'administrators',
            'suspended_deactivated_accounts',
        ];
    @endphp

    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_users.description') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_users.results', ['from' => $users->firstItem() ?? 0, 'to' => $users->lastItem() ?? 0, 'total' => $users->total()]) }}</p>
    </header>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('messages.admin_users.tabs') }}">
        @foreach ($userPages as $userPage)
            @php
                $userPageRoute = config("admin_dashboard.page_routes.{$userPage}");
            @endphp
            <a
                href="{{ route($userPageRoute, ['lang' => app()->getLocale()]) }}"
                @if ($page === $userPage) aria-current="page" @endif
                class="admin-nav-link inline-flex min-h-10 items-center border px-3 py-2 text-sm font-semibold {{ $page === $userPage ? 'is-active' : '' }}"
            >
                {{ __('messages.admin_nav.pages.'.$userPage) }}
            </a>
        @endforeach
    </nav>

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

    <form method="GET" action="{{ route($currentRoute, ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="grid min-w-0 gap-1.5 sm:col-span-2">
            <label for="users-search" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.search') }}</label>
            <input id="users-search" name="search" type="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm" placeholder="{{ __('messages.admin_users.search_placeholder') }}">
        </div>

        @if (! $lockedRole && ! $deactivatedOnly)
            <div class="grid min-w-0 gap-1.5">
                <label for="users-role" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.role') }}</label>
                <select id="users-role" name="role" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                    <option value="">{{ __('messages.admin_users.all_roles') }}</option>
                    @foreach ($roleOptions as $roleOption)
                        <option value="{{ $roleOption->value }}" @selected(($filters['role'] ?? '') === $roleOption->value)>{{ __('messages.admin_users.roles.'.$roleOption->value) }}</option>
                    @endforeach
                </select>
            </div>
        @elseif ($lockedRole)
            <div class="grid min-w-0 gap-1.5">
                <span class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.role') }}</span>
                <span class="admin-input flex min-h-10 items-center border px-3 text-sm">{{ __('messages.admin_users.roles.'.$lockedRole->value) }}</span>
            </div>
        @endif

        @if (! $deactivatedOnly)
            <div class="grid min-w-0 gap-1.5">
                <label for="users-status" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.status') }}</label>
                <select id="users-status" name="status" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
                    <option value="">{{ __('messages.admin_users.all_statuses') }}</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('messages.admin_users.statuses.active') }}</option>
                    <option value="deactivated" @selected(($filters['status'] ?? '') === 'deactivated')>{{ __('messages.admin_users.statuses.deactivated') }}</option>
                </select>
            </div>
        @endif

        <div class="grid min-w-0 gap-1.5">
            <label for="users-from" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.registered_from') }}</label>
            <input id="users-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>

        <div class="grid min-w-0 gap-1.5">
            <label for="users-to" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.registered_to') }}</label>
            <input id="users-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="admin-input min-h-10 w-full min-w-0 border px-3 text-sm">
        </div>

        @if ($errors->any())
            <div class="text-sm font-semibold text-red-700 sm:col-span-2 xl:col-span-4" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="flex min-w-0 flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-4">
            <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center gap-2 border px-4 py-2 text-sm font-semibold">
                <x-admin.icon name="search" class="h-4 w-4" />
                {{ __('messages.admin_users.apply_filters') }}
            </button>
            <a href="{{ route($currentRoute, ['lang' => app()->getLocale()]) }}" class="admin-nav-link inline-flex min-h-10 items-center border px-4 py-2 text-sm font-semibold">{{ __('messages.admin_users.clear_filters') }}</a>
            @if (($filters['search'] ?? '') !== '')
                <p class="text-sm admin-muted" aria-live="polite">{{ __('messages.admin_users.searching_for', ['term' => $filters['search']]) }}</p>
            @endif
        </div>
    </form>

    @if ($deactivatedOnly)
        <p class="text-xs admin-muted">{{ __('messages.admin_users.deactivation_note') }}</p>
    @endif

    @if ($users->isEmpty())
        <div class="admin-empty-state grid justify-items-center gap-2 border p-8 text-center" data-users-empty>
            <h2 class="text-base font-bold admin-heading">{{ __('messages.admin_users.empty_title') }}</h2>
            <p class="max-w-xl text-sm leading-6 admin-muted">{{ __('messages.admin_users.empty_description') }}</p>
        </div>
    @else
        @if (! $lockedRole || $lockedRole !== \App\Enums\UserRole::ADMIN)
            <form id="admin-users-bulk" method="POST" action="{{ route('admin.users.bulk', ['lang' => app()->getLocale()]) }}" class="admin-empty-state grid min-w-0 gap-4 border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" onsubmit='return confirm(@json(__('messages.admin_users.confirm_bulk')))'>
                @csrf
                <input type="hidden" name="list" value="{{ $page }}">
                <input type="hidden" name="list_page" value="{{ $currentPage }}">
                @foreach ($filters as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach
                <div class="grid min-w-0 gap-1.5">
                    <label for="bulk-operation" class="text-xs font-semibold admin-heading">{{ __('messages.admin_users.bulk_action') }}</label>
                    <select id="bulk-operation" name="operation" required class="admin-input min-h-10 w-full border px-3 text-sm sm:max-w-md">
                        <option value="deactivate">{{ __('messages.admin_users.deactivate_selected') }}</option>
                        <option value="restore">{{ __('messages.admin_users.restore_selected') }}</option>
                        <option value="permanently_delete">{{ __('messages.admin_users.delete_selected') }}</option>
                    </select>
                </div>
                <button type="submit" class="admin-nav-link inline-flex min-h-10 items-center justify-center gap-2 border px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">
                    <x-admin.icon name="verified" class="h-4 w-4" />
                    {{ __('messages.admin_users.apply_to_selected') }}
                </button>
            </form>
        @endif

        <div class="admin-empty-state overflow-x-auto border">
            <table class="w-full min-w-[64rem] text-left text-sm">
                <thead class="border-b admin-border">
                    <tr class="text-xs uppercase tracking-wide admin-muted">
                        @if (! $lockedRole || $lockedRole !== \App\Enums\UserRole::ADMIN)
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.select') }}</th>
                        @endif
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.name') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.email') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.role') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.status') }}</th>
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.presence') }}</th>
                        @if ($agencyOwnersPage)
                            <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.agency') }}</th>
                        @endif
                        <th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_users.registered') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">{{ __('messages.admin_users.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y admin-border">
                    @foreach ($users as $user)
                        <tr class="align-top transition hover:bg-[var(--admin-surface-soft)] focus-within:bg-[var(--admin-surface-soft)]">
                            @if (! $lockedRole || $lockedRole !== \App\Enums\UserRole::ADMIN)
                                <td class="px-4 py-4">
                                    @if ($user->role !== \App\Enums\UserRole::ADMIN)
                                        <input form="admin-users-bulk" type="checkbox" name="user_ids[]" value="{{ $user->id }}" aria-label="{{ __('messages.admin_users.select_user', ['name' => $user->name]) }}" class="h-4 w-4 rounded border">
                                    @else
                                        <span class="text-xs admin-muted">{{ __('messages.admin_users.protected') }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border admin-border bg-[var(--admin-surface-soft)]" data-user-list-photo>
                                        @if ($user->profile_photo_path)
                                            <img src="{{ $user->profilePhotoUrl() }}" alt="{{ __('messages.admin_users.profile_photo_alt', ['name' => $user->name]) }}" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-sm font-bold admin-muted" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                                        @endif
                                    </span>
                                    <span class="min-w-0 break-words font-semibold admin-heading">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-4 admin-muted">{{ $user->email }}</td>
                            <td class="px-4 py-4">
                                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold">{{ __('messages.admin_users.roles.'.$user->role->value) }}</span>
                            </td>
                            <td
                                class="px-4 py-4 {{ $user->deleted_at ? 'bg-rose-50/80 dark:bg-rose-950/30' : 'bg-emerald-50/80 dark:bg-emerald-950/30' }}"
                                data-user-status-cell="{{ $user->deleted_at ? 'deactivated' : 'active' }}"
                            >
                                <span class="admin-badge inline-flex border px-2 py-1 text-xs font-bold {{ $user->deleted_at ? 'border-rose-500/40 bg-rose-500/10 text-rose-800 dark:text-rose-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200' }}">
                                    {{ $user->deleted_at ? __('messages.admin_users.statuses.deactivated') : __('messages.admin_users.statuses.active') }}
                                </span>
                            </td>
                            @php
                                $isUserOnline = ! $user->deleted_at && $user->isOnline();
                            @endphp
                            <td class="px-4 py-4" data-user-presence-cell="{{ $isUserOnline ? 'online' : 'offline' }}">
                                <span class="inline-flex items-center gap-2 text-xs admin-muted" data-user-presence="{{ $isUserOnline ? 'online' : 'offline' }}">
                                    <span class="h-2 w-2 rounded-full {{ $isUserOnline ? 'bg-emerald-500' : 'bg-slate-400' }}" aria-hidden="true"></span>
                                    <span>{{ $isUserOnline ? __('messages.admin_users.online_now') : __('messages.admin_users.offline') }}</span>
                                </span>
                                @if (! $isUserOnline && $user->last_active_at)
                                    <span class="mt-1 block text-xs admin-muted">{{ __('messages.admin_users.last_active', ['time' => $user->last_active_at->locale(app()->getLocale())->diffForHumans()]) }}</span>
                                @elseif (! $isUserOnline)
                                    <span class="mt-1 block text-xs admin-muted">{{ __('messages.admin_users.never_active') }}</span>
                                @endif
                            </td>
                            @if ($agencyOwnersPage)
                                <td class="px-4 py-4 admin-muted">
                                    @if ($user->agency)
                                        <span class="font-semibold admin-heading">{{ $user->agency->company_name }}</span>
                                        <span class="block text-xs">{{ __('messages.admin_users.verification.'.$user->agency->verification_status->value) }} · {{ $user->agency->deleted_at ? __('messages.admin_users.agency_statuses.deactivated') : __('messages.admin_users.agency_statuses.active') }}</span>
                                    @else
                                        {{ __('messages.admin_users.unavailable') }}
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-4 admin-muted">{{ $user->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.show', ['userId' => $user->id, 'lang' => app()->getLocale(), 'return_page' => $page, 'list_page' => $currentPage, ...$filters]) }}" class="admin-nav-link inline-flex min-h-9 items-center justify-center border px-3 py-1.5 text-xs font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">{{ __('messages.admin_users.view_details') }}</a>
                                    @if ($user->role !== \App\Enums\UserRole::ADMIN)
                                        <form method="POST" action="{{ route('admin.users.destroy', ['userId' => $user->id, 'lang' => app()->getLocale()]) }}" onsubmit='return confirm(@json(__('messages.admin_users.confirm_permanent_delete', ['name' => $user->name])))' data-user-delete-form>
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="return_page" value="{{ $page }}">
                                            <input type="hidden" name="list_page" value="{{ $currentPage }}">
                                            @foreach ($filters as $filterName => $filterValue)
                                                <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                                            @endforeach
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center border border-rose-500/40 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-800 focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-rose-200">
                                                {{ __('messages.admin_users.delete_user') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
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
