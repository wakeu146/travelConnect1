<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.notifications')" :title="__('messages.notifications')" :description="'Stay on top of new traveler activity, reply requests, and booking updates.'" />

    <x-agency.panel class="mb-6 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold">Activity inbox</p>
                <p class="agency-text-subtle mt-1 text-xs">{{ $unreadCount }} unread notifications</p>
            </div>
            @if ($unreadCount > 0)
                <form action="{{ route('agency.notifications.read-all', ['lang' => app()->getLocale()]) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-bold">Mark all as read</button>
                </form>
            @endif
        </div>
    </x-agency.panel>

    <div class="space-y-4">
        @forelse ($notifications as $notification)
            @php($data = $notification->data ?? [])
            <x-agency.panel class="p-5" data-agency-notification>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="agency-text-primary text-base font-bold">{{ $data['title'] ?? __('messages.notifications') }}</p>
                            @if (is_null($notification->read_at))
                                <span class="rounded-full bg-[#fff1ed] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] text-[#e76f51]">New</span>
                            @endif
                        </div>
                        <p class="agency-text-subtle mt-3 text-sm leading-6">{{ $data['message'] ?? 'No additional details were provided.' }}</p>
                        <p class="agency-text-subtle mt-3 text-[11px] uppercase tracking-[.12em]">
                            {{ $notification->created_at->translatedFormat('d M Y · H:i') }}
                        </p>
                    </div>

                    @if (is_null($notification->read_at))
                        <form action="{{ route('agency.notifications.read', ['notification' => $notification->id, 'lang' => app()->getLocale()]) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="agency-button-secondary rounded-lg border px-3 py-2 text-[10px] font-bold">Mark read</button>
                        </form>
                    @endif
                </div>

                @if (! empty($data['url']))
                    <div class="mt-4">
                        <a href="{{ $data['url'] }}" class="agency-accent-text text-sm font-bold">View details</a>
                    </div>
                @endif
            </x-agency.panel>
        @empty
            <x-agency.empty-state :title="'No notifications yet'" :description="'Traveler messages, reviews, and booking updates will appear here.'" />
        @endforelse
    </div>

    @if ($notifications->hasPages())
        <div class="mt-6">{{ $notifications->links() }}</div>
    @endif
</x-agency-shell>
