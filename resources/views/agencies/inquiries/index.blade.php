<x-agency-shell>
    <x-agency.page-header :eyebrow="__('messages.inquiries')" :title="__('messages.recent_inquiries')" :description="__('messages.inquiries_needing_response')" />

    @if (session('inquiry_close_status'))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-[#dfeae8] bg-[#edfaf6] px-4 py-3 text-sm font-semibold text-[#1d5d4e]" role="status">
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#2f9e7a] text-xs text-white" aria-hidden="true">✓</span>
            <span>{{ session('inquiry_close_status') }}</span>
        </div>
    @endif

    <x-agency.panel class="mb-6 p-4">
        <form method="GET" action="{{ route('agency.inquiries.index', ['lang' => app()->getLocale()]) }}" class="grid gap-3 md:grid-cols-[1fr_160px_auto] md:items-end">
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="inquiry-search">
                {{ __('messages.search') }}
                <input id="inquiry-search" name="search" value="{{ $search }}" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal" placeholder="{{ __('messages.search') }}">
            </label>
            <label class="text-xs font-bold uppercase tracking-[.16em] agency-text-subtle" for="inquiry-status">
                {{ __('messages.status') }}
                <select id="inquiry-status" name="status" class="agency-input mt-2 w-full rounded-xl border px-4 py-3 text-sm font-normal normal-case tracking-normal">
                    <option value="">{{ __('messages.all_statuses') }}</option>
                    @foreach (['open', 'responded', 'closed'] as $inquiryState)
                        <option value="{{ $inquiryState }}" @selected($status === $inquiryState)>{{ __('messages.inquiry_status_'.$inquiryState) }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="agency-button-primary rounded-xl px-4 py-3 text-sm font-bold">{{ __('messages.search') }}</button>
        </form>
    </x-agency.panel>

    <div class="space-y-4">
        @forelse ($inquiries as $inquiry)
            @php
                $inquiryStatus = $inquiry->getRawOriginal('status');
                $inquiryBadgeClass = match ($inquiryStatus) {
                    'open' => 'agency-pill-accent',
                    'responded' => 'agency-pill-warning',
                    'closed' => 'agency-pill-success',
                    default => 'agency-pill-neutral',
                };
            @endphp
            <x-agency.panel>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="agency-text-primary text-base font-bold">{{ $inquiry->subject }}</p>
                            <span class="agency-pill {{ $inquiryBadgeClass }} w-fit rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em]">{{ __('messages.inquiry_status_'.$inquiryStatus) }}</span>
                        </div>
                        <p class="agency-text-subtle mt-2 text-xs">{{ $inquiry->user?->name ?? __('messages.traveler') }} · {{ $inquiry->created_at->translatedFormat('d M Y') }}</p>
                        <p class="agency-text-subtle mt-4 whitespace-pre-line text-sm leading-6">{{ $inquiry->message }}</p>

                        @if ($inquiry->hasReply())
                            <div class="mt-4 rounded-xl border border-[#dfeae8] bg-[#f7faf9] p-3">
                                <p class="text-[10px] font-bold uppercase tracking-[.15em] text-[#45707d]">{{ __('messages.reply') }}</p>
                                <p class="mt-2 text-sm text-[#173042]">{{ $inquiry->reply_message }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col items-start gap-2 lg:items-end">
                        @if ($inquiryStatus === 'closed')
                            <form action="{{ route('agency.inquiries.destroy', ['inquiry' => $inquiry->id, 'lang' => app()->getLocale()]) }}" method="POST" onsubmit="return confirm('{{ __('messages.delete_inquiry_confirm') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" data-inquiry-delete-icon class="delete-icon-button" aria-label="{{ __('messages.delete_closed_inquiry') }}" title="{{ __('messages.delete_closed_inquiry') }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>
                                </button>
                            </form>
                        @else
                            <form action="{{ route('agency.inquiries.close', ['inquiry' => $inquiry->id, 'lang' => app()->getLocale()]) }}" method="POST">
                                @csrf
                                <button type="submit" class="agency-button-secondary rounded-lg border px-3 py-2 text-[10px] font-bold">{{ __('messages.close_inquiry') }}</button>
                            </form>
                        @endif

                        @unless ($inquiry->hasReply() || $inquiryStatus === 'closed')
                            <form action="{{ route('agency.inquiries.reply', ['inquiry' => $inquiry->id, 'lang' => app()->getLocale()]) }}" method="POST" class="flex w-full max-w-md flex-col gap-2 lg:items-end">
                                @csrf
                                <label for="reply-message-{{ $inquiry->id }}" class="sr-only">{{ __('messages.reply_message') }}</label>
                                <textarea id="reply-message-{{ $inquiry->id }}" name="reply_message" rows="3" class="agency-input w-full rounded-xl border px-3 py-2 text-sm" placeholder="{{ __('messages.reply_to_traveler') }}" required></textarea>
                                <button type="submit" class="agency-button-primary rounded-lg px-3 py-2 text-[10px] font-bold text-white">{{ __('messages.send_reply') }}</button>
                            </form>
                        @endunless
                    </div>
                </div>
            </x-agency.panel>
        @empty
            <x-agency.empty-state :title="__('messages.no_recent_inquiries')" :description="__('messages.inquiries_needing_response')" />
        @endforelse
    </div>

    @if ($inquiries->hasPages())
        <div class="mt-6">{{ $inquiries->links() }}</div>
    @endif
</x-agency-shell>
