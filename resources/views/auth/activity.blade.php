<x-account-shell title="{{ __('messages.my_activity') }} | TravelConnect">
    <main class="min-h-screen bg-[#f7f8f6] px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <div class="mx-auto max-w-5xl"><div class="border-b border-[#e4e9e7] pb-7"><p class="text-xs font-bold uppercase tracking-[.2em] text-[#e76f51]">{{ __('messages.my_activity') }}</p><h1 class="mt-2 font-serif text-4xl tracking-tight">{{ __('messages.activity_title') }}</h1><p class="mt-3 text-sm text-[#607985]">{{ __('messages.activity_intro') }}</p></div>
            <section class="mt-8">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-serif text-2xl">{{ __('messages.notifications') }}</h2>
                    @if ($notificationCount > 0)
                        <span class="rounded-full bg-[#fff1ed] px-2.5 py-1 text-xs font-bold text-[#e76f51]">{{ $notificationCount }} {{ __('messages.unread_notifications') }}</span>
                    @endif
                </div>
                <div class="mt-4 grid gap-3">
                    @forelse ($notifications as $notification)
                        @php($data = $notification->data ?? [])
                        <article class="dashboard-panel border-[#e7eceb] bg-white p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <h3 class="font-bold">{{ $data['title'] ?? __('messages.notifications') }}</h3>
                                    @if (is_null($notification->read_at))
                                        <span class="shrink-0 rounded-full bg-[#edf4f3] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.12em] text-[#2f9e7a]">New</span>
                                    @endif
                                </div>
                                <form action="{{ route('account.notifications.destroy', ['notification' => $notification->id, 'lang' => app()->getLocale()]) }}" method="POST" class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" data-notification-delete-icon class="flex h-9 w-9 items-center justify-center rounded-lg text-[#607985] transition hover:bg-[#fdf0ee] hover:text-[#c9483b]" aria-label="{{ __('messages.delete_notification') }}" title="{{ __('messages.delete_notification') }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg>
                                    </button>
                                </form>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-[#607985]">{{ $data['message'] ?? '' }}</p>
                            @if (!empty($data['url']))
                                <a href="{{ $data['url'] }}" class="mt-3 inline-flex text-sm font-bold text-[#e76f51] hover:text-[#b94d35]">{{ __('messages.view_all') }}</a>
                            @endif
                        </article>
                    @empty
                        <div class="dashboard-panel border-dashed border-[#cbd9d8] bg-transparent p-6 text-sm text-[#607985]">{{ __('messages.no_notifications') }}</div>
                    @endforelse
                </div>
            </section>
            <div class="mt-8 grid gap-8 lg:grid-cols-2"><section><h2 class="font-serif text-2xl">{{ __('messages.open_inquiries') }}</h2><div class="mt-4 grid gap-3">@forelse ($inquiries as $inquiry)<article class="dashboard-panel border-[#e7eceb] bg-white p-5"><div class="flex items-center justify-between gap-3"><h3 class="font-bold">{{ $inquiry->subject }}</h3><span class="rounded-full bg-[#edf4f3] px-2.5 py-1 text-xs font-bold text-[#2f9e7a]">{{ __('messages.inquiry_status_'.$inquiry->status->value) }}</span></div>@if ($inquiry->hasReply())<p class="mt-3 rounded-lg bg-[#f7f8f6] p-3 text-sm text-[#173042]">{{ $inquiry->reply_message }}</p>@endif<p class="mt-2 text-sm text-[#607985]">{{ $inquiry->agency->company_name }}</p></article>@empty<div class="dashboard-panel border-dashed border-[#cbd9d8] bg-transparent p-6 text-sm text-[#607985]">{{ __('messages.no_inquiries_yet') }}</div>@endforelse</div></section><section><h2 class="font-serif text-2xl">{{ __('messages.reviews_written') }}</h2><div class="mt-4 grid gap-3">@forelse ($reviews as $review)<article class="dashboard-panel border-[#e7eceb] bg-white p-5"><div class="flex items-center justify-between gap-3"><h3 class="font-bold">{{ $review->agency->company_name }}</h3><span class="text-[#e76f51]">{{ $review->rating }} ★</span></div><p class="mt-2 text-sm leading-6 text-[#607985]">{{ $review->comment }}</p></article>@empty<div class="dashboard-panel border-dashed border-[#cbd9d8] bg-transparent p-6 text-sm text-[#607985]">{{ __('messages.no_reviews_yet') }}</div>@endforelse</div></section></div></div>
    </main>
</x-account-shell>
