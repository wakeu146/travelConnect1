<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAgencyManagementService;
use App\Services\AdminUserManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AdminUserController extends Controller
{
    public function index(Request $request, AdminDashboardController $dashboard): View
    {
        return $this->listing($request, $dashboard, 'all_users');
    }

    public function travelers(Request $request, AdminDashboardController $dashboard): View
    {
        return $this->listing($request, $dashboard, 'travelers', UserRole::TRAVELER);
    }

    public function agencyOwners(Request $request, AdminDashboardController $dashboard): View
    {
        return $this->listing($request, $dashboard, 'agency_owners', UserRole::AGENCY_OWNER);
    }

    public function administrators(Request $request, AdminDashboardController $dashboard): View
    {
        return $this->listing($request, $dashboard, 'administrators', UserRole::ADMIN);
    }

    public function suspended(Request $request, AdminDashboardController $dashboard): View
    {
        return $this->listing($request, $dashboard, 'suspended_deactivated_accounts', deactivatedOnly: true);
    }

    public function show(Request $request, int $userId, AdminDashboardController $dashboard): View
    {
        $user = User::withTrashed()->findOrFail($userId);
        $listPage = $request->validate([
            'return_page' => ['nullable', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filters = $this->validateFilters($request);

        if ($user->role === UserRole::AGENCY_OWNER) {
            $user->load([
                'agency' => fn (HasOne $query) => $query
                    ->withTrashed()
                    ->select(['id', 'user_id', 'company_name', 'verification_status', 'deleted_at']),
            ]);
        }

        if ($user->role === UserRole::TRAVELER) {
            $user->load([
                'bookings' => fn (HasMany $query) => $query
                    ->select(['id', 'user_id', 'agency_id', 'booking_reference', 'status', 'created_at'])
                    ->with('agency:id,company_name')
                    ->latest()
                    ->limit(10),
                'travelRequests' => fn (HasMany $query) => $query
                    ->select(['id', 'user_id', 'title', 'destination', 'status', 'created_at'])
                    ->latest()
                    ->limit(10),
                'inquiries' => fn (HasMany $query) => $query
                    ->select(['id', 'user_id', 'agency_id', 'subject', 'status', 'created_at'])
                    ->with('agency:id,company_name')
                    ->latest()
                    ->limit(10),
                'reviews' => fn (HasMany $query) => $query
                    ->select(['id', 'user_id', 'agency_id', 'rating', 'status', 'created_at'])
                    ->with('agency:id,company_name')
                    ->latest()
                    ->limit(10),
            ]);
        }

        if ($user->role === UserRole::ADMIN) {
            $user->load([
                'adminLoginEvents' => fn (HasMany $query) => $query
                    ->select(['id', 'user_id', 'outcome', 'failed_step', 'created_at'])
                    ->latest('created_at')
                    ->limit(10),
            ]);
        }

        $user->load([
            'adminActivities' => fn (HasMany $query) => $query
                ->with('actor:id,name')
                ->latest('created_at')
                ->latest('id')
                ->limit(50),
        ]);
        $user->loadCount('adminActivities');

        $page = $listPage['return_page'] ?? 'all_users';
        $pageRoutes = config('admin_dashboard.page_routes', []);
        $backRoute = $pageRoutes[$page];
        $backFilters = $filters;
        if (($listPage['list_page'] ?? 1) > 1) {
            $backFilters['page'] = $listPage['list_page'];
        }
        $backUrl = route($backRoute, [
            'lang' => app()->getLocale(),
            ...array_filter($backFilters, fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
        $detailContext = array_filter([
            'return_page' => $page,
            'list_page' => $listPage['list_page'] ?? null,
            ...$filters,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return $dashboard->renderUsersPage(
            $request,
            $page,
            ['userDetails' => $user, 'backUrl' => $backUrl, 'detailContext' => $detailContext],
            __('messages.admin_users.details_title'),
        );
    }

    public function destroy(
        Request $request,
        int $userId,
        AdminUserManagementService $management,
        AdminAgencyManagementService $agencyManagement,
    ): RedirectResponse {
        $data = $request->validate([
            'return_page' => ['required', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('to'), ['before_or_equal:to'])],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $emailSent = $management->permanentlyDelete(
            $request->user(),
            $userId,
            $agencyManagement,
        );
        $returnFilters = array_intersect_key($data, array_flip(['search', 'role', 'status', 'from', 'to']));

        if (($data['list_page'] ?? 1) > 1) {
            $returnFilters['page'] = $data['list_page'];
        }

        $response = redirect()->route($this->routeForPage($data['return_page']), [
            'lang' => app()->getLocale(),
            ...$returnFilters,
        ])->with('status', __('messages.admin_users.permanently_deleted'));

        if ($emailSent === false) {
            $response->with('error', __('messages.admin_users.permanently_deleted_email_failed'));
        }

        return $response;
    }

    public function deactivate(Request $request, int $userId, AdminUserManagementService $management): RedirectResponse
    {
        $data = $request->validate([
            'return_page' => ['nullable', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $emailSent = $management->deactivate($request->user(), $userId);

        return $this->returnToDetails($userId, $data)->with(
            $emailSent ? 'status' : 'error',
            $emailSent
                ? __('messages.admin_users.deactivated_successfully')
                : __('messages.admin_users.deactivated_email_failed'),
        );
    }

    public function revokeSessions(Request $request, int $userId, AdminUserManagementService $management): RedirectResponse
    {
        $data = $request->validate([
            'return_page' => ['nullable', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('to'), ['before_or_equal:to'])],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $emailSent = $management->revokeSessions($request->user(), $userId);

        return $this->returnToDetails($userId, $data)->with(
            $emailSent ? 'status' : 'error',
            $emailSent
                ? __('messages.admin_users.sessions_revoked_successfully')
                : __('messages.admin_users.sessions_revoked_email_failed'),
        );
    }

    public function restore(Request $request, int $userId, AdminUserManagementService $management): RedirectResponse
    {
        $data = $request->validate([
            'return_page' => ['nullable', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $emailSent = $management->restore($request->user(), $userId);

        return $this->returnToDetails($userId, $data)->with(
            $emailSent ? 'status' : 'error',
            $emailSent
                ? __('messages.admin_users.restored_successfully')
                : __('messages.admin_users.restored_email_failed'),
        );
    }

    public function note(Request $request, int $userId, AdminUserManagementService $management): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:4000'],
            'return_page' => ['nullable', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $management->addNote($request->user(), $userId, trim($data['note']));

        return $this->returnToDetails($userId, $data)->with('status', __('messages.admin_users.note_added'));
    }

    public function passwordReset(Request $request, int $userId, AdminUserManagementService $management): RedirectResponse
    {
        $data = $request->validate([
            'return_page' => ['nullable', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $sent = $management->sendPasswordResetInstructions($request->user(), $userId);

        return $this->returnToDetails($userId, $data)->with(
            $sent ? 'status' : 'error',
            $sent ? __('messages.admin_users.reset_sent') : __('messages.admin_users.reset_failed'),
        );
    }

    public function bulk(
        Request $request,
        AdminUserManagementService $management,
        AdminAgencyManagementService $agencyManagement,
    ): RedirectResponse {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:25'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'operation' => ['required', Rule::in(['deactivate', 'restore', 'permanently_delete'])],
            'list' => ['required', Rule::in(array_keys(config('admin_dashboard.page_routes')))],
            'list_page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $processed = 0;
        $failed = 0;
        $emailFailed = 0;
        $filters = array_intersect_key($data, array_flip(['search', 'role', 'status', 'from', 'to']));
        $listPage = (int) ($data['list_page'] ?? 1);
        $eligibleIds = $this->queryForPage($data['list'], $filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($listPage, 25)
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        foreach ($data['user_ids'] as $userId) {
            if (! in_array((int) $userId, $eligibleIds, true)) {
                $failed++;

                continue;
            }

            try {
                if ($data['operation'] === 'permanently_delete') {
                    if ($management->permanentlyDelete(
                        $request->user(),
                        (int) $userId,
                        $agencyManagement,
                    ) === false) {
                        $emailFailed++;
                    }
                } elseif ($data['operation'] === 'deactivate') {
                    if (! $management->deactivate($request->user(), (int) $userId)) {
                        $emailFailed++;
                    }
                } else {
                    if (! $management->restore($request->user(), (int) $userId)) {
                        $emailFailed++;
                    }
                }
                $processed++;
            } catch (ValidationException|HttpExceptionInterface $exception) {
                $failed++;
            }
        }

        $pageRoute = config('admin_dashboard.page_routes.'.$data['list']);

        $response = redirect()->route($pageRoute, [
            'lang' => app()->getLocale(),
            ...((isset($data['list_page']) && $data['list_page'] > 1) ? ['page' => $data['list_page']] : []),
            ...$filters,
        ]);
        $message = $emailFailed > 0
            ? __('messages.admin_users.bulk_result_with_email_failures', [
                'processed' => $processed,
                'failed' => $failed,
                'email_failed' => $emailFailed,
            ])
            : __('messages.admin_users.bulk_result', ['processed' => $processed, 'failed' => $failed]);

        return $response->with($emailFailed > 0 ? 'error' : 'status', $message);
    }

    private function listing(
        Request $request,
        AdminDashboardController $dashboard,
        string $page,
        ?UserRole $lockedRole = null,
        bool $deactivatedOnly = false,
    ): View {
        $filters = $this->validateFilters($request);

        $query = $this->queryForPage($page, $filters, $lockedRole, $deactivatedOnly)
            ->select([
                'id',
                'name',
                'email',
                'profile_photo_path',
                'role',
                'is_online',
                'last_active_at',
                'created_at',
                'deleted_at',
            ]);

        if ($page === 'agency_owners') {
            $query->with([
                'agency' => fn (HasOne $agencyQuery) => $agencyQuery
                    ->withTrashed()
                    ->select(['id', 'user_id', 'company_name', 'verification_status', 'deleted_at']),
            ]);
        }

        $users = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $summary = $page === 'all_users' ? [
            'total' => User::withTrashed()->count(),
            'active' => User::query()->count(),
            'deactivated' => User::onlyTrashed()->count(),
        ] : null;

        return $dashboard->renderUsersPage($request, $page, [
            'users' => $users,
            'summary' => $summary,
            'filters' => $filters,
            'page' => $page,
            'currentRoute' => $this->routeForPage($page),
            'roleOptions' => UserRole::cases(),
            'lockedRole' => $lockedRole,
            'deactivatedOnly' => $deactivatedOnly,
            'agencyOwnersPage' => $page === 'agency_owners',
            'currentPage' => $users->currentPage(),
        ]);
    }

    private function validateFilters(Request $request): array
    {
        $filters = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))],
            'status' => ['nullable', Rule::in(['active', 'deactivated'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ])->validate();

        if (isset($filters['search'])) {
            $filters['search'] = trim(preg_replace('/\s+/u', ' ', $filters['search']) ?? $filters['search']);
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function queryForPage(
        string $page,
        array $filters,
        ?UserRole $lockedRole = null,
        bool $deactivatedOnly = false,
    ): Builder {
        $lockedRole ??= match ($page) {
            'travelers' => UserRole::TRAVELER,
            'agency_owners' => UserRole::AGENCY_OWNER,
            'administrators' => UserRole::ADMIN,
            default => null,
        };
        $deactivatedOnly = $deactivatedOnly || $page === 'suspended_deactivated_accounts';

        return User::withTrashed()
            ->when($lockedRole, fn (Builder $users) => $users->where('role', $lockedRole->value))
            ->when(isset($filters['role']), fn (Builder $users) => $users->where('role', $filters['role']))
            ->when($deactivatedOnly, fn (Builder $users) => $users->whereNotNull('deleted_at'))
            ->when(! $deactivatedOnly && ($filters['status'] ?? null) === 'active', fn (Builder $users) => $users->whereNull('deleted_at'))
            ->when(! $deactivatedOnly && ($filters['status'] ?? null) === 'deactivated', fn (Builder $users) => $users->whereNotNull('deleted_at'))
            ->when(isset($filters['search']) && $filters['search'] !== '', function (Builder $users) use ($filters): void {
                $users->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when(isset($filters['from']), fn (Builder $users) => $users->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $users) => $users->whereDate('created_at', '<=', $filters['to']));
    }

    private function returnToDetails(int $userId, array $data): RedirectResponse
    {
        $returnData = array_intersect_key($data, array_flip(['return_page', 'list_page', 'search', 'role', 'status', 'from', 'to']));

        return redirect()->route('admin.users.show', [
            'userId' => $userId,
            'lang' => app()->getLocale(),
            ...$returnData,
        ]);
    }

    private function routeForPage(string $page): string
    {
        return match ($page) {
            'travelers' => 'admin.users.travelers',
            'agency_owners' => 'admin.users.agency-owners',
            'administrators' => 'admin.users.administrators',
            'suspended_deactivated_accounts' => 'admin.users.suspended',
            default => 'admin.users.index',
        };
    }
}
