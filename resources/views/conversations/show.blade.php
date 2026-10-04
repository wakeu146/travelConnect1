@php
    $isAgencyThread = str_starts_with((string) ($backRoute ?? ''), 'agency.');
@endphp

@if ($isAgencyThread)
    <x-agency-shell>
        <div class="mx-auto max-w-5xl">
            <div class="mb-6 flex items-center justify-between gap-3">
                <a href="{{ route($backRoute, ['lang' => app()->getLocale()]) }}" class="agency-button-secondary rounded-lg border px-3 py-2 text-xs font-bold">Back</a>
                <span class="agency-pill agency-pill-neutral rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em]">{{ $conversation->status === 'closed' ? 'Closed' : 'Open' }}</span>
            </div>

            <x-agency.panel class="mb-6 p-5">
                <p class="agency-text-subtle text-[10px] font-bold uppercase tracking-[.16em]">Conversation</p>
                <h1 class="mt-3 text-2xl font-bold">{{ $conversation->subject ?: 'Conversation' }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm agency-text-subtle">
                    <span>{{ $conversation->traveler?->name ?? 'Traveler' }}</span>
                    @if ($conversation->booking)
                        <span>•</span>
                        <span>{{ $conversation->booking->booking_reference ?? 'Booking' }}</span>
                    @endif
                </div>
            </x-agency.panel>

            <div class="space-y-4 rounded-2xl border border-[#e4e9e7] bg-white p-4 shadow-sm">
                @forelse ($messages as $message)
                    @php
                        $isMine = $message->sender_id === auth()->id();
                    @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $isMine ? 'bg-[#173042] text-white' : 'border border-[#dbe3e5] bg-[#f7f8f6] text-[#173042]' }}">
                            <div class="mb-2 flex items-center justify-between gap-3 text-[10px] font-bold uppercase tracking-[.12em] {{ $isMine ? 'text-[#dfe8ef]' : 'text-[#607985]' }}">
                                <span>{{ $message->sender?->name ?? 'User' }}</span>
                                <span>{{ $message->created_at->translatedFormat('d M Y · H:i') }}</span>
                            </div>
                            <p class="whitespace-pre-line text-sm leading-6">{{ $message->body }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-[#dbe3e5] bg-[#f7f8f6] p-6 text-sm text-[#607985]">No messages in this conversation yet.</div>
                @endforelse
            </div>

            @if ($conversation->status === 'open')
                <form action="{{ route($sendRoute, ['conversation' => $conversation->id, 'lang' => app()->getLocale()]) }}" method="POST" class="mt-6 rounded-2xl border border-[#e4e9e7] bg-white p-4 shadow-sm">
                    @csrf
                    <label for="conversation-message" class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle">Reply</label>
                    <textarea id="conversation-message" name="body" rows="4" class="agency-input mt-3 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal" placeholder="Write a reply to the traveler" required></textarea>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">Send message</button>
                    </div>
                </form>
            @endif

            @if ($messages->hasPages())
                <div class="mt-6">{{ $messages->links() }}</div>
            @endif
        </div>
    </x-agency-shell>
@else
    <x-account-shell title="{{ $conversation->subject ?: 'Conversation' }} | TravelConnect">
        <main class="min-h-screen bg-[#f7f8f6] px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
            <div class="mx-auto max-w-5xl">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <a href="{{ route($backRoute, ['lang' => app()->getLocale()]) }}" class="inline-flex items-center rounded-xl border border-[#dbe3e5] bg-white px-3 py-2 text-xs font-bold text-[#173042]">Back</a>
                    <span class="rounded-full bg-[#edf4f3] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] text-[#2f9e7a]">{{ $conversation->status === 'closed' ? 'Closed' : 'Open' }}</span>
                </div>

                <section class="rounded-2xl border border-[#e4e9e7] bg-white p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-[.16em] text-[#607985]">Conversation</p>
                    <h1 class="mt-3 font-serif text-3xl tracking-tight">{{ $conversation->subject ?: 'Conversation' }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-[#607985]">
                        <span>{{ $conversation->agency?->company_name ?? 'Agency' }}</span>
                        @if ($conversation->booking)
                            <span>•</span>
                            <span>{{ $conversation->booking->booking_reference ?? 'Booking' }}</span>
                        @endif
                    </div>
                </section>

                <div class="mt-6 space-y-4 rounded-2xl border border-[#e4e9e7] bg-white p-4 shadow-sm">
                    @forelse ($messages as $message)
                        @php
                            $isMine = $message->sender_id === auth()->id();
                        @endphp
                        <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[80%] rounded-2xl px-4 py-3 {{ $isMine ? 'bg-[#173042] text-white' : 'border border-[#dbe3e5] bg-[#f7f8f6] text-[#173042]' }}">
                                <div class="mb-2 flex items-center justify-between gap-3 text-[10px] font-bold uppercase tracking-[.12em] {{ $isMine ? 'text-[#dfe8ef]' : 'text-[#607985]' }}">
                                    <span>{{ $message->sender?->name ?? 'User' }}</span>
                                    <span>{{ $message->created_at->translatedFormat('d M Y · H:i') }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm leading-6">{{ $message->body }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-[#dbe3e5] bg-[#f7f8f6] p-6 text-sm text-[#607985]">No messages in this conversation yet.</div>
                    @endforelse
                </div>

                @if ($conversation->status === 'open')
                    <form action="{{ route($sendRoute, ['conversation' => $conversation->id, 'lang' => app()->getLocale()]) }}" method="POST" class="mt-6 rounded-2xl border border-[#e4e9e7] bg-white p-4 shadow-sm">
                        @csrf
                        <label for="conversation-message" class="text-xs font-bold uppercase tracking-[.16em] text-[#607985]">Reply</label>
                        <textarea id="conversation-message" name="body" rows="4" class="mt-3 w-full rounded-xl border border-[#dbe3e5] bg-[#f7f8f6] px-4 py-3 text-sm text-[#173042] outline-none focus:border-[#e76f51]" placeholder="Write a message" required></textarea>
                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="rounded-xl bg-[#173042] px-4 py-3 text-sm font-bold text-white">Send message</button>
                        </div>
                    </form>
                @endif

                @if ($messages->hasPages())
                    <div class="mt-6">{{ $messages->links() }}</div>
                @endif
            </div>
        </main>
    </x-account-shell>
@endif
