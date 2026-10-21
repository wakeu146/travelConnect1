<x-account-shell :title="$issue->subject">
    <main class="mx-auto grid max-w-7xl gap-8 px-5 py-8 text-[#173042] sm:px-8 lg:px-10 lg:py-10">
        <header class="grid gap-3">
            <a href="{{ route('account.issues.index', ['lang' => app()->getLocale()]) }}" class="w-fit text-sm font-semibold text-[#607985] underline underline-offset-4 transition hover:text-[#e76f51]">{{ __('messages.traveler_issues.back_to_reports') }}</a>
            <p class="text-xs font-bold uppercase tracking-[.18em] text-[#e76f51]">{{ __('messages.traveler_issues.title') }}</p>
        </header>

        @if (session('status'))
            <p role="status" class="border border-emerald-500/40 bg-emerald-500/10 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</p>
        @endif

        <article class="dashboard-panel grid max-w-4xl gap-6 border border-[#e7eceb] bg-white p-5 sm:p-8">
            <div class="grid gap-4 border-b border-[#e7eceb] pb-6 sm:grid-cols-[1fr_auto] sm:items-start">
                <div class="grid gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-[#607985]">{{ __('messages.traveler_issues.types.'.$issue->type) }}</span>
                        <span aria-hidden="true" class="text-[#9aadb2]">·</span>
                        <span class="inline-flex items-center border px-2.5 py-1 text-xs font-bold text-[#173042] {{ $issue->status === 'resolved' ? 'border-emerald-500/30 bg-emerald-500/10' : ($issue->status === 'in_review' ? 'border-sky-500/30 bg-sky-500/10' : 'border-amber-500/30 bg-amber-500/10') }}">{{ __('messages.traveler_issues.statuses.'.$issue->status) }}</span>
                    </div>
                    <h1 class="break-words font-serif text-3xl tracking-tight sm:text-4xl">{{ $issue->subject }}</h1>
                </div>
                <time class="text-xs text-[#607985]" datetime="{{ $issue->created_at->toIso8601String() }}">{{ $issue->created_at->locale(app()->getLocale())->isoFormat('LLL') }}</time>
            </div>

            @if ($issue->booking)
                <div class="grid gap-1 border-l-2 border-[#e76f51] bg-[#f7f8f6] px-4 py-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-[#607985]">{{ __('messages.traveler_issues.booking_optional') }}</span>
                    <span class="text-sm font-bold">{{ $issue->booking->booking_reference }}</span>
                </div>
            @endif

            <section class="grid gap-3">
                <h2 class="text-sm font-bold">{{ __('messages.traveler_issues.description') }}</h2>
                <p class="whitespace-pre-wrap break-words text-sm leading-7 text-[#405b68]">{{ $issue->description }}</p>
            </section>
        </article>
    </main>
</x-account-shell>
