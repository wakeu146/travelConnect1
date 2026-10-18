<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return $this->renderDashboard($request, 'main', 'overview');
    }

    public function section(Request $request, string $section): View
    {
        $sections = $this->sections();
        abort_unless(array_key_exists($section, $sections), 404);

        return $this->renderDashboard($request, $section);
    }

    public function page(Request $request, string $page): View
    {
        $sections = $this->sections();

        foreach ($sections as $sectionKey => $section) {
            foreach ($section['groups'] as $group) {
                $pages = $group['pages'] ?? [$group['page']];
                if (in_array($page, $pages, true)) {
                    return $this->renderDashboard(
                        $request,
                        $sectionKey,
                        $page,
                        $group['label'] ?? null,
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
