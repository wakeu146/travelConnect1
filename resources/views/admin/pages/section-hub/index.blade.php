<section class="grid gap-6" aria-label="{{ __('messages.admin_nav.sections.'.$activeSection) }}" data-admin-section-hub="{{ $activeSection }}">
    <p class="max-w-3xl text-sm leading-6 admin-muted">{{ __('messages.admin_nav.section_hub_intros.'.$activeSection) }}</p>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($sections[$activeSection]['groups'] as $group)
            @php
                $pages = $group['pages'] ?? [$group['page']];
                $entryPage = $pages[0];
                $groupKey = $group['label'] ?? $group['page'];
                $pageRoute = config("admin_dashboard.page_routes.{$entryPage}");
                $entryUrl = $entryPage === 'overview'
                    ? route('admin.dashboard', ['lang' => app()->getLocale()])
                    : ($pageRoute
                        ? route($pageRoute, ['lang' => app()->getLocale()])
                        : route('admin.dashboard.page', ['page' => $entryPage, 'lang' => app()->getLocale()]));
            @endphp
            <a href="{{ $entryUrl }}" class="admin-empty-state group grid min-h-48 content-between gap-6 border p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 dark:hover:border-slate-600" data-admin-section-card="{{ $groupKey }}">
                <span class="grid gap-4">
                    <span class="admin-empty-icon flex h-11 w-11 items-center justify-center rounded-xl">
                        <x-admin.icon :name="$group['icon']" class="h-5 w-5" />
                    </span>
                    <span class="grid gap-2">
                        <span class="text-base font-bold admin-heading">
                            {{ isset($group['label']) ? __('messages.admin_nav.groups.'.$group['label']) : __('messages.admin_nav.pages.'.$group['page']) }}
                        </span>
                        <span class="text-sm leading-6 admin-muted">{{ __('messages.admin_nav.section_hub_descriptions.'.$groupKey) }}</span>
                    </span>
                </span>
                <span class="flex items-center justify-between gap-3 border-t pt-3 text-xs font-semibold admin-border admin-heading">
                    <span>{{ __('messages.admin_nav.section_hub_open') }}</span>
                    <span class="admin-badge rounded-full px-2 py-1">{{ count($pages) }}</span>
                </span>
            </a>
        @endforeach
    </div>
</section>
