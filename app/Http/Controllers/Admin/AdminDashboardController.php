<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\AdminTravelerIssueReceived;
use App\Services\AdminAlertQueue;
use App\Services\AdminOverviewData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminOverviewData $overviewData): View
    {
        return $this->renderDashboard($request, 'main', 'overview', overview: $overviewData->get());
    }

    public function section(Request $request, string $section): View
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
    ): View {
        $sections = $this->sections();

        foreach ($sections as $sectionKey => $section) {
            foreach ($section['groups'] as $group) {
                $pages = $group['pages'] ?? [$group['page']];
                if (in_array($page, $pages, true)) {
                    $alerts = null;
                    $alertDetail = null;

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

                    return $this->renderDashboard(
                        $request,
                        $sectionKey,
                        $page,
                        $group['label'] ?? null,
                        $page === 'overview' ? $overviewData->get() : null,
                        $alerts,
                        $alertDetail,
                    );
                }
            }
        }

        abort(404);
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
    ): View {
        $sections = $this->sections();
        $sectionTitle = __("messages.admin_nav.sections.{$section}");
        $pageTitle = $page
            ? __("messages.admin_nav.pages.{$page}")
            : $sectionTitle;
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
            'adminNotifications' => $request->user()
                ->notifications()
                ->where('type', AdminTravelerIssueReceived::class)
                ->latest()
                ->limit(5)
                ->get(),
            'unreadAdminNotificationCount' => $request->user()
                ->unreadNotifications()
                ->where('type', AdminTravelerIssueReceived::class)
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
                        'url' => route('admin.dashboard.page', ['page' => $page, 'lang' => $locale]),
                    ];
                }
            }
        }

        return $items;
    }
}
