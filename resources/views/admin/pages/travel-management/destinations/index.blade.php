<section class="grid gap-6" data-admin-travel-destinations>
    <header class="grid gap-2">
        <p class="text-sm leading-6 admin-muted">{{ __('messages.admin_travel.destinations_existing_fields') }}</p>
        <p class="text-xs admin-muted">{{ __('messages.admin_travel.destination_result_count', ['count' => $destinations->total()]) }}</p>
    </header>
    @if ($destinations->isNotEmpty())
        <div class="admin-empty-state overflow-x-auto border">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="border-b admin-border"><tr class="text-xs uppercase tracking-wide admin-muted"><th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.destination') }}</th><th scope="col" class="px-4 py-3 font-semibold">{{ __('messages.admin_travel.package_count') }}</th><th scope="col" class="px-4 py-3 font-semibold"></th></tr></thead>
                <tbody class="divide-y admin-border">
                    @foreach ($destinations as $destination)
                        <tr>
                            <td class="px-4 py-3 font-semibold admin-heading">{{ $destination->destination }}</td>
                            <td class="px-4 py-3">{{ $destination->packages_count }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.dashboard.page', ['page' => 'all_travel_packages', 'lang' => app()->getLocale(), 'destination' => $destination->destination]) }}" class="admin-nav-link inline-flex min-h-9 items-center border px-3 py-1.5 text-xs font-semibold">{{ __('messages.admin_travel.view_destination_packages') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $destinations->links() }}
    @else
        <p class="admin-empty-state border p-6 text-sm admin-muted">{{ __('messages.admin_travel.destinations_empty') }}</p>
    @endif
</section>
