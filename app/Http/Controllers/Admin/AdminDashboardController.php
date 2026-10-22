<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Package;
use App\Notifications\AdminAgencyDocumentReceived;
use App\Notifications\AdminSecurityAlertReceived;
use App\Notifications\AdminTravelerIssueReceived;
use App\Services\AdminAgencyManagementService;
use App\Services\AdminAlertQueue;
use App\Services\AdminOverviewData;
use App\Services\AdminTravelManagementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminOverviewData $overviewData): View
    {
        return $this->renderDashboard($request, 'main', 'overview', overview: $overviewData->get());
    }

    public function section(Request $request, string $section): View|RedirectResponse
    {
        $sections = $this->sections();
        abort_unless(array_key_exists($section, $sections), 404);

        return $this->renderDashboard($request, $section);
    }

    public function page(
        Request $request,
        string $page,
        AdminOverviewData $overviewData,
        AdminAlertQueue $alertQueue,
        AdminAgencyManagementService $agencyManagement,
        AdminTravelManagementService $travelManagement,
    ): View|RedirectResponse {
        $pageRoute = config("admin_dashboard.page_routes.{$page}");
        if ($pageRoute !== null) {
            $query = $request->query();
            unset($query['lang']);

            return redirect()->route($pageRoute, [
                'lang' => app()->getLocale(),
                ...$query,
            ]);
        }

        $sections = $this->sections();

        foreach ($sections as $sectionKey => $section) {
            foreach ($section['groups'] as $group) {
                $pages = $group['pages'] ?? [$group['page']];
                if (in_array($page, $pages, true)) {
                    $alerts = null;
                    $alertDetail = null;
                    $agencyDirectory = null;
                    $travelManagementData = null;

                    if ($page === 'alerts') {
                        $filterOptions = $alertQueue->filterOptions();
                        $filters = $request->validate([
                            'search' => ['nullable', 'string', 'max:120'],
                            'status' => ['nullable', Rule::in(['all', ...$filterOptions['statuses']])],
                            'severity' => ['nullable', Rule::in(['all', ...$filterOptions['severities']])],
                            'category' => ['nullable', Rule::in(['all', ...$filterOptions['categories']])],
                            'source' => ['required_with:source_id', Rule::in(array_keys($alertQueue->sourceDefinitions()))],
                            'source_id' => ['required_with:source', 'integer', 'min:1'],
                        ]);

                        if (isset($filters['source'])) {
                            $alertDetail = $alertQueue->detail($filters['source'], (int) $filters['source_id']);
                        } else {
                            $alerts = $alertQueue->listing($filters);
                        }
                    }

                    if (in_array($page, [
                        'all_agencies',
                        'pending_verification',
                        'verified_agencies',
                        'unverified_agencies',
                        'suspended_agencies',
                    ], true)) {
                        $filters = $request->validate([
                            'search' => ['nullable', 'string', 'max:120'],
                            'verification_status' => ['nullable', Rule::in(['all', 'pending', 'verified', 'rejected'])],
                            'operating_status' => ['nullable', Rule::in(['all', 'active', 'suspended'])],
                            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
                            'created_from' => ['nullable', 'date'],
                            'created_to' => ['nullable', 'date'],
                        ]);
                        if (isset($filters['created_from'], $filters['created_to'])
                            && $filters['created_from'] > $filters['created_to']) {
                            throw ValidationException::withMessages([
                                'created_to' => __('messages.admin_agencies.filter_date_error'),
                            ]);
                        }
                        $agencyDirectory = $agencyManagement->listing($page, $filters);
                    }

                    if (in_array($page, [
                        'all_travel_packages',
                        'published_packages',
                        'draft_unpublished_packages',
                        'reported_pending_review',
                    ], true)) {
                        $filters = $request->validate([
                            'search' => ['nullable', 'string', 'max:120'],
                            'status' => ['nullable', Rule::in(['all', 'unpublished', 'draft', 'published', 'archived'])],
                            'destination' => ['nullable', 'string', 'max:191'],
                        ]);
                        $travelManagementData = [
                            ...$travelManagement->packages($page, $filters),
                            'destinations' => Package::query()
                                ->whereNotNull('destination')
                                ->where('destination', '!=', '')
                                ->distinct()
                                ->orderBy('destination')
                                ->pluck('destination'),
                            'reportsAvailable' => false,
                        ];
                    } elseif ($page === 'travel_services') {
                        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
                        $travelManagementData = $travelManagement->services($filters);
                    } elseif ($page === 'countries') {
                        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
                        $travelManagementData = $travelManagement->countries($filters);
                    } elseif ($page === 'destinations') {
                        $travelManagementData = $travelManagement->destinations();
                    }

                    return $this->renderDashboard(
                        $request,
                        $sectionKey,
                        $page,
                        $group['label'] ?? null,
                        $page === 'overview' ? $overviewData->get() : null,
                        $alerts,
                        $alertDetail,
                        $agencyDirectory,
                        travelManagementData: $travelManagementData,
                    );
                }
            }
        }

        abort(404);
    }

    public function agency(
        Request $request,
        Agency $agency,
        AdminAgencyManagementService $agencyManagement,
    ): View {
        $profile = $agencyManagement->profile($agency, $request);

        return $this->renderDashboard(
            $request,
            'platform_management',
            'all_agencies',
            'agencies',
            agencyProfile: $profile,
        );
    }

    /**
     * Render a Users module page inside the existing administrator dashboard shell.
     *
     * @param  array<string, mixed>  $usersPageData
     */
    public function renderUsersPage(
        Request $request,
        string $page,
        array $usersPageData,
        ?string $pageTitle = null,
    ): View {
        return $this->renderDashboard(
            $request,
            'platform_management',
            $page,
            'users',
            usersPageData: $usersPageData,
            pageTitleOverride: $pageTitle,
        );
    }

    /**
     * @param  array<string, mixed>  $travelManagementData
     */
    public function renderTravelManagementPage(
        Request $request,
        string $page,
        array $travelManagementData,
        ?string $pageTitle = null,
    ): View {
        return $this->renderDashboard(
            $request,
            'platform_management',
            $page,
            'travel_management',
            travelManagementData: $travelManagementData,
            pageTitleOverride: $pageTitle,
        );
    }

    /**
     * @return array<string, array{icon: string, groups: array<int, array<string, mixed>>}>
     */
    private function sections(): array
    {
        return config('admin_dashboard.sections');
    }

    private function renderDashboard(
        Request $request,
        string $section,
        ?string $page = null,
        ?string $module = null,
        ?array $overview = null,
        ?LengthAwarePaginator $alerts = null,
        ?array $alertDetail = null,
        ?array $agencyDirectory = null,
        ?array $agencyProfile = null,
        ?array $usersPageData = null,
        ?array $travelManagementData = null,
        ?string $pageTitleOverride = null,
    ): View {
        $sections = $this->sections();
        $adminNotifications = $request->user()
            ->unreadNotifications()
            ->whereIn('type', [
                AdminSecurityAlertReceived::class,
                AdminTravelerIssueReceived::class,
                AdminAgencyDocumentReceived::class,
            ])
            ->latest()
            ->limit(5)
            ->get();
        $sectionTitle = __("messages.admin_nav.sections.{$section}");
        $pageTitle = $pageTitleOverride ?? ($page
            ? __("messages.admin_nav.pages.{$page}")
            : $sectionTitle);
        $breadcrumbs = $page
            ? array_filter([
                $sectionTitle,
                $module ? __("messages.admin_nav.groups.{$module}") : null,
                $pageTitle,
            ])
            : [__('messages.admin_nav.dashboard'), $sectionTitle];

        return view('admin.dashboard', [
            'admin' => $request->user(),
            'sections' => $sections,
            'activeSection' => $section,
            'activePage' => $page,
            'activeModule' => $module,
            'sectionTitle' => $sectionTitle,
            'pageTitle' => $pageTitle,
            'breadcrumbs' => $breadcrumbs,
            'navigationSearchItems' => $this->navigationSearchItems($sections),
            'overview' => $overview,
            'alerts' => $alerts,
            'alertDetail' => $alertDetail,
            'agencyDirectory' => $agencyDirectory,
            'agencyProfile' => $agencyProfile,
            'usersPageData' => $usersPageData,
            'travelManagementData' => $travelManagementData,
            'adminNotifications' => $adminNotifications,
            'unreadAdminSecurityNotificationCount' => $request->user()
                ->unreadNotifications()
                ->where('type', AdminSecurityAlertReceived::class)
                ->count(),
            'unreadAdminNotificationCount' => $request->user()
                ->unreadNotifications()
                ->whereIn('type', [
                    AdminSecurityAlertReceived::class,
                    AdminTravelerIssueReceived::class,
                    AdminAgencyDocumentReceived::class,
                ])
                ->count(),
        ]);
    }

    /**
     * @param  array<string, array{icon: string, groups: array<int, array<string, mixed>>}>  $sections
     * @return array<int, array{label: string, url: string}>
     */
    private function navigationSearchItems(array $sections): array
    {
        $items = [];
        $locale = app()->getLocale();

        foreach ($sections as $sectionKey => $section) {
            $items[] = [
                'label' => __("messages.admin_nav.sections.{$sectionKey}"),
                'url' => $sectionKey === 'main'
                    ? route('admin.dashboard', ['lang' => $locale])
                    : route('admin.dashboard.section', ['section' => $sectionKey, 'lang' => $locale]),
            ];

            foreach ($section['groups'] as $group) {
                foreach ($group['pages'] ?? [$group['page']] as $page) {
                    $items[] = [
                        'label' => __("messages.admin_nav.pages.{$page}"),
                        'url' => isset(config('admin_dashboard.page_routes')[$page])
                            ? route(config("admin_dashboard.page_routes.{$page}"), ['lang' => $locale])
                            : route('admin.dashboard.page', ['page' => $page, 'lang' => $locale]),
                    ];
                }
            }
        }

        return $items;
    }
}
