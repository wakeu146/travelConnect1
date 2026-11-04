<x-account-shell :title="__('messages.traveler_issues.title')">
    <main class="mx-auto grid max-w-7xl gap-8 px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <header class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div class="grid gap-2">
                <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.traveler_issues.title') }}</p>
                <h1 class="font-serif text-3xl tracking-tight sm:text-4xl">{{ __('messages.traveler_issues.my_reports') }}</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#607985]">{{ __('messages.traveler_issues.intro') }}</p>
            </div>
            <a href="{{ route('account.issues.create', ['lang' => app()->getLocale()]) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 bg-[#e76f51] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#d95f42]">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('messages.traveler_issues.new_report') }}
            </a>
        </header>

        @if (session('status'))
            <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>
        @endif

        <section class="grid gap-3" aria-label="{{ __('messages.traveler_issues.my_reports') }}">
            @forelse ($issues as $issue)
                <a href="{{ route('account.issues.show', ['issue' => $issue->id, 'lang' => app()->getLocale()]) }}" class="dashboard-panel group grid gap-4 border border-[#e7eceb] bg-white p-5 transition hover:border-[#e76f51] sm:grid-cols-[1fr_auto] sm:items-center sm:px-6">
                    <div class="grid min-w-0 gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-[#607985]">{{ __('messages.traveler_issues.types.'.$issue->type) }}</span>
                            <span aria-hidden="true" class="text-[#9aadb2]">·</span>
                            <span class="inline-flex items-center border px-2.5 py-1 text-xs font-bold text-[#173042] {{ $issue->status === 'resolved' ? 'border-emerald-500/30 bg-emerald-500/10' : ($issue->status === 'dismissed' ? 'border-rose-500/30 bg-rose-500/10' : ($issue->status === 'in_review' ? 'border-sky-500/30 bg-sky-500/10' : 'border-amber-500/30 bg-amber-500/10')) }}">{{ __('messages.traveler_issues.statuses.'.$issue->status) }}</span>
                        </div>
                        <span class="break-words text-lg font-bold transition group-hover:text-[#e76f51]">{{ $issue->subject }}</span>
                        @if ($issue->booking)
                            <span class="text-sm text-[#607985]">{{ __('messages.traveler_issues.booking_reference', ['reference' => $issue->booking->booking_reference]) }}</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-[#e7eceb] pt-3 text-xs text-[#607985] sm:justify-end sm:border-0 sm:pt-0">
                        <time datetime="{{ $issue->created_at->toIso8601String() }}">{{ $issue->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4 shrink-0 transition group-hover:translate-x-0.5 group-hover:text-[#e76f51]" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </a>
            @empty
                <div class="dashboard-panel grid justify-items-center gap-3 border border-dashed border-[#cbd9d8] bg-white px-6 py-12 text-center">
                    <span class="flex h-12 w-12 items-center justify-center bg-[#f1f6f4] text-[#e76f51]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6" aria-hidden="true"><path d="M12 8v4m0 4h.01M5 20h14a1 1 0 0 0 .87-1.5l-7-12a1 1 0 0 0-1.74 0l-7 12A1 1 0 0 0 5 20Z"/></svg>
                    </span>
                    <h2 class="font-bold">{{ __('messages.traveler_issues.empty_title') }}</h2>
                    <p class="max-w-md text-sm leading-6 text-[#607985]">{{ __('messages.traveler_issues.empty_description') }}</p>
                    <a href="{{ route('account.issues.create', ['lang' => app()->getLocale()]) }}" class="mt-2 inline-flex min-h-10 items-center justify-center bg-[#173042] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#26495d]">{{ __('messages.traveler_issues.new_report') }}</a>
                </div>
            @endforelse
        </section>

        {{ $issues->links() }}
    </main>
</x-account-shell>
