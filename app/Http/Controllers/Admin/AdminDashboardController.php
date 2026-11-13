<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Package;
use App\Notifications\AdminAgencyDocumentReceived;
use App\Notifications\AdminAgencyRegistered;
use App\Notifications\AdminCustomRequestReceived;
use App\Notifications\AdminSecurityAlertReceived;
use App\Notifications\AdminTravelerIssueReceived;
use App\Services\AdminAgencyManagementService;
use App\Services\AdminAlertQueue;
use App\Services\AdminAnalyticsManagementService;
use App\Services\AdminFinanceManagementService;
use App\Services\AdminOverviewData;
use App\Services\AdminReviewReportManagementService;
use App\Services\AdminTravelManagementService;
use App\Services\AdminTrustScoreManagementService;
use App\Services\CountryCatalogueSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function exportAnalytics(
        Request $request,
        AdminAnalyticsManagementService $analyticsManagement,
    ): StreamedResponse {
        $filters = $this->analyticsFilters($request);
        $format = $request->validate([
            'format' => ['required', Rule::in(['csv', 'json'])],
        ])['format'];
        $analyticsData = $analyticsManagement->pageData($filters);
        $filename = 'app-analytics-'.$analyticsData['from'].'-'.$analyticsData['to'].'.'.$format;

        if ($format === 'json') {
            return response()->streamDownload(function () use ($analyticsData): void {
                echo json_encode([
                    'from' => $analyticsData['from'],
                    'to' => $analyticsData['to'],
                    'daily' => $analyticsData['daily'],
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($analyticsManagement, $analyticsData): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open the analytics export stream.');
            }

            fwrite($stream, "\xEF\xBB\xBF");
            foreach ($analyticsManagement->csvRows($analyticsData) as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function page(
        Request $request,
        string $page,
        AdminOverviewData $overviewData,
        AdminAlertQueue $alertQueue,
        AdminAgencyManagementService $agencyManagement,
        AdminTravelManagementService $travelManagement,
        CountryCatalogueSynchronizer $countryCatalogue,
        AdminReviewReportManagementService $reviewReportManagement,
        AdminTrustScoreManagementService $trustScoreManagement,
        AdminFinanceManagementService $financeManagement,
        AdminAnalyticsManagementService $analyticsManagement,
    ): View|RedirectResponse {
        $pageRoute = config("admin_dashboard.page_routes.{$page}");
        if ($pageRoute !== null) {
            $query = $request->query();
            unset($query['lang']);
            if (in_array($page, [
                'all_requests',
                'new_requests',
                'in_progress_requests',
                'quoted_requests',
                'accepted_converted_requests',
                'closed_requests',
            ], true)) {
                $query['queue'] = $page;
            }

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
                    $reviewReportData = null;
                    $trustScoreData = null;
                    $financeData = null;
                    $analyticsData = null;

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
                        'verification_pending',
                        'approved',
                        'rejected',
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
                        $travelManagementData = $travelManagement->countries($filters, $countryCatalogue);
                    } elseif ($page === 'destinations') {
                        $travelManagementData = $travelManagement->destinations();
                    } elseif (in_array($page, ['reviews_ratings', 'reported_content', 'user_agency_complaints'], true)) {
                        $statuses = match ($page) {
                            'reviews_ratings' => ['all', 'pending', 'published', 'flagged'],
                            'reported_content' => ['all', 'open', 'investigating', 'dismissed', 'restricted', 'removed'],
                            'user_agency_complaints' => ['all', 'open', 'in_review', 'resolved'],
                        };
                        $filters = $request->validate([
                            'search' => ['nullable', 'string', 'max:120'],
                            'status' => ['nullable', Rule::in($statuses)],
                        ]);
                        $reviewReportData = $reviewReportManagement->pageData($page, $filters);
                    } elseif (in_array($page, ['trust_score_overview', 'agencies_requiring_attention'], true)) {
                        $filters = $request->validate([
                            'search' => ['nullable', 'string', 'max:120'],
                        ]);
                        $trustScoreData = $trustScoreManagement->pageData($page, $filters['search'] ?? null);
                    } elseif (in_array($page, [
                        'financial_overview',
                        'all_transactions',
                        'successful_payments',
                        'pending_failed_payments',
                        'refunds',
                        'payment_disputes',
                        'financial_reports',
                    ], true)) {
                        $filters = $request->validate([
                            'created_from' => ['nullable', 'date'],
                            'created_to' => ['nullable', 'date'],
                        ]);
                        if (isset($filters['created_from'], $filters['created_to'])
                            && $filters['created_from'] > $filters['created_to']) {
                            throw ValidationException::withMessages([
                                'created_to' => __('messages.admin_finance.filter_date_error'),
                            ]);
                        }
                        $financeData = $financeManagement->pageData($page, $filters);
                    }

                    if (in_array($page, [
                        'platform_performance',
                        'user_growth',
                        'agency_growth',
                        'booking_analytics',
                        'popular_destinations',
                        'package_performance',
                        'trust_verification_statistics',
                        'export_reports',
                    ], true)) {
                        $analyticsData = $analyticsManagement->pageData($this->analyticsFilters($request));
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
                        reviewReportData: $reviewReportData,
                        trustScoreData: $trustScoreData,
                        financeData: $financeData,
                        analyticsData: $analyticsData,
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
        $context = $request->validate([
            'queue' => ['nullable', Rule::in(['verification_pending', 'approved', 'rejected'])],
        ])['queue'] ?? null;
        $profile = $agencyManagement->profile($agency, $request);

        return $this->renderDashboard(
            $request,
            $context ? 'trust_safety' : 'platform_management',
            $context ?? 'all_agencies',
            $context ? 'verification' : 'agencies',
            agencyProfile: $profile,
        );
    }

    /**
     * Render custom-request operations within the existing administrator shell.
     *
     * @param  array{view: string, data: array<string, mixed>}  $customRequestData
     */
    public function renderCustomRequests(Request $request, string $page, array $customRequestData): View
    {
        return $this->renderDashboard(
            $request,
            'platform_management',
            $page,
            'custom_travel_requests',
            customRequestData: $customRequestData,
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
     * @param  array<string, mixed>  $bookingPageData
     */
    public function renderBookingsModule(
        Request $request,
        string $page,
        string $view,
        array $bookingPageData,
        ?string $pageTitle = null,
    ): View {
        return $this->renderDashboard(
            $request,
            'platform_management',
            $page,
            'bookings',
            pageTitleOverride: $pageTitle,
            bookingPageData: ['view' => $view, 'data' => $bookingPageData],
        );
    }

    /**
     * Render an individual review or report within the administrator dashboard shell.
     *
     * @param  array<string, mixed>  $detailData
     */
    public function renderReviewReportDetail(
        Request $request,
        string $page,
        string $view,
        array $detailData,
        string $pageTitle,
    ): View {
        return $this->renderDashboard(
            $request,
            'trust_safety',
            $page,
            'reviews_reports',
            reviewReportData: ['detailView' => $view, ...$detailData],
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
        ?array $reviewReportData = null,
        ?array $trustScoreData = null,
        ?string $pageTitleOverride = null,
        ?array $bookingPageData = null,
        ?array $customRequestData = null,
        ?array $financeData = null,
        ?array $analyticsData = null,
    ): View {
        $sections = $this->sections();
        $adminNotifications = $request->user()
            ->unreadNotifications()
            ->whereIn('type', [
                AdminSecurityAlertReceived::class,
                AdminTravelerIssueReceived::class,
                AdminAgencyDocumentReceived::class,
                AdminAgencyRegistered::class,
                AdminCustomRequestReceived::class,
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
            'bookingPageData' => $bookingPageData,
            'customRequestData' => $customRequestData,
            'reviewReportData' => $reviewReportData,
            'trustScoreData' => $trustScoreData,
            'financeData' => $financeData,
            'analyticsData' => $analyticsData,
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
                    AdminAgencyRegistered::class,
                    AdminCustomRequestReceived::class,
                ])
                ->count(),
        ]);
    }

    private function analyticsFilters(Request $request): array
    {
        $filters = $request->validate([
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
        ]);

        $to = isset($filters['created_to'])
            ? CarbonImmutable::parse($filters['created_to'])
            : CarbonImmutable::instance(now());
        $from = isset($filters['created_from'])
            ? CarbonImmutable::parse($filters['created_from'])
            : $to->subDays(29);

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'created_to' => __('messages.admin_analytics.filter_date_error'),
            ]);
        }

        return $filters;
    }

    /**
     * @param  array<string, array{icon: string, groups: array<int, array<string, mixed>>}>  $sections
     * @return array<int, array{label: string, context: ?string, url: string}>
     */
    private function navigationSearchItems(array $sections): array
    {
        $items = [];
        $locale = app()->getLocale();

        foreach ($sections as $sectionKey => $section) {
            $items[] = [
                'label' => __("messages.admin_nav.sections.{$sectionKey}"),
                'context' => null,
                'url' => $sectionKey === 'main'
                    ? route('admin.dashboard', ['lang' => $locale])
                    : route('admin.dashboard.section', ['section' => $sectionKey, 'lang' => $locale]),
            ];

            foreach ($section['groups'] as $group) {
                $context = __("messages.admin_nav.sections.{$sectionKey}");

                if (isset($group['label'])) {
                    $context .= ' / '.__("messages.admin_nav.groups.{$group['label']}");
                }

                foreach ($group['pages'] ?? [$group['page']] as $page) {
                    $items[] = [
                        'label' => __("messages.admin_nav.pages.{$page}"),
                        'context' => $context,
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
