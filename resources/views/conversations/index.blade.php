<x-account-shell title="Messages | TravelConnect">
    <main class="min-h-screen bg-[#f7f8f6] px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <div class="mx-auto max-w-5xl">
            <div class="border-b border-[#e4e9e7] pb-7">
                <p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">Messages</p>
                <h1 class="mt-2 font-serif text-4xl tracking-tight">Your conversations</h1>
                <p class="mt-3 text-sm text-[#607985]">Keep track of agency replies, travel updates, and open planning discussions.</p>
            </div>

            <section class="mt-8">
                <form method="GET" action="{{ route('account.conversations.index', ['lang' => app()->getLocale()]) }}" class="flex flex-col gap-3 rounded-2xl border border-[#e4e9e7] bg-white p-4 sm:flex-row sm:items-end">
                    <label for="conversation-search" class="flex-1 text-xs font-bold uppercase tracking-[.16em] text-[#607985]">
                        Search
                        <input id="conversation-search" name="search" value="{{ $search }}" class="mt-2 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm font-normal text-[#173042] outline-none focus:border-[#e76f51]" placeholder="Search conversations">
                    </label>
                    <button type="submit" class="rounded-xl bg-[#173042] px-4 py-3 text-sm font-bold text-white">Search</button>
                </form>

                <div class="mt-6 space-y-4">
                    @forelse ($conversations as $conversation)
                        @php
                            $agency = $conversation->agency;
                            $latestMessage = $conversation->latestMessage;
                            $unreadCount = (int) ($conversation->unread_messages_count ?? 0);
                        @endphp
                        <article class="rounded-2xl border border-[#e4e9e7] bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('account.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()]) }}" class="text-base font-bold text-[#173042] hover:text-[#e76f51]">
                                            {{ $conversation->subject ?: 'Conversation' }}
                                        </a>
                                        @if ($unreadCount > 0)
                                            <span class="rounded-full bg-[#fff1ed] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] text-[#e76f51]">{{ $unreadCount }} unread</span>
                                        @endif
                                    </div>

                                    <p class="mt-2 text-xs text-[#607985]">
                                        {{ $agency?->company_name ?? 'Agency' }} · {{ $conversation->updated_at->translatedFormat('d M Y') }}
                                    </p>

                                    @if ($latestMessage)
                                        <p class="mt-4 text-sm leading-6 text-[#607985]">{{ \Illuminate\Support\Str::limit($latestMessage->body, 180) }}</p>
                                    @else
                                        <p class="mt-4 text-sm leading-6 text-[#607985]">No messages yet.</p>
                                    @endif
                                </div>

                                <a href="{{ route('account.conversations.show', ['conversation' => $conversation->id, 'lang' => app()->getLocale()]) }}" class="inline-flex rounded-xl bg-[#f1f6f4] px-3 py-2 text-xs font-bold text-[#173042] hover:bg-[#e8f0ee]">Open</a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[#cbd9d8] bg-transparent p-6 text-sm text-[#607985]">No conversations yet. Start by sending an inquiry to an agency.</div>
                    @endforelse
                </div>
            </section>

            @if ($conversations->hasPages())
                <div class="mt-6">{{ $conversations->links() }}</div>
            @endif
        </div>
    </main>
</x-account-shell>
