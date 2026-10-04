<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.inquiries')" :title="'Conversations'" :description="'Track ongoing traveler discussions and their latest updates.'" />

    <x-agency.panel class="mb-6 p-4">
        <form method="GET" action="{{ route('agency.conversations.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 md:grid-cols-[1fr_160px_auto] md:items-end">
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="conversation-search">
                Search
                <input id="conversation-search" name="search" value="{{ $search }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal" placeholder="Search conversations">
            </label>
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="conversation-status">
                Status
                <select id="conversation-status" name="status" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
                    <option value="">All statuses</option>
                    <option value="open" @selected($status === 'open')>Open</option>
                    <option value="closed" @selected($status === 'closed')>Closed</option>
                </select>
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">Search</button>
        </form>
    </x-agency.panel>

    <div class="space-y-4">
        @forelse ($conversations as $conversation)
            @php
                $traveler = $conversation->traveler;
                $latestMessage = $conversation->latestMessage;
                $statusLabel = match ($conversation->status) {
                    'open' => 'Open',
                    'closed' => 'Closed',
                    default => ucfirst((string) $conversation->status),
                };
                $statusTone = $conversation->status === 'closed' ? 'agency-pill-neutral' : 'agency-pill-accent';
            @endphp

            <x-agency.panel>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('agency.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()]) }}" class="agency-accent-text text-base font-bold">
                                {{ $conversation->subject ?: 'Conversation' }}
                            </a>
                            <span class="agency-pill {{ $statusTone }} w-fit rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em]">{{ $statusLabel }}</span>
                            @if (($conversation->unread_messages_count ?? 0) > 0)
                                <span class="rounded-full bg-[#fff1ed] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] text-[#e76f51]">
                                    {{ $conversation->unread_messages_count }} unread
                                </span>
                            @endif
                        </div>

                        <p class="agency-text-subtle mt-2 text-xs">
                            {{ $traveler?->name ?? 'Traveler' }} · {{ $conversation->updated_at->translatedFormat('d M Y') }}
                        </p>

                        @if ($latestMessage)
                            <p class="agency-text-subtle mt-4 line-clamp-3 text-sm leading-6">
                                {{ \Illuminate\Support\Str::limit($latestMessage->body, 180) }}
                            </p>
                        @else
                            <p class="agency-text-subtle mt-4 text-sm leading-6">No messages yet.</p>
                        @endif
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('agency.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()]) }}" class="agency-button-primary rounded-lg px-3 py-2 text-xs font-bold">Open</a>
                    </div>
                </div>
            </x-agency.panel>
        @empty
            <x-agency.empty-state :title="'No conversations yet'" :description="'Traveler threads will appear here as soon as a new inquiry or message is sent.'" />
        @endforelse
    </div>

    @if ($conversations->hasPages())
        <div class="mt-6">{{ $conversations->links() }}</div>
    @endif
</x-agency-shell>
