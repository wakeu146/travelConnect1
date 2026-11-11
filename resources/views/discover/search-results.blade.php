<div id="agency-discovery-results" data-discovery-results class="{{ count($agencyCards) ? 'grid' : 'hidden' }} gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($agencyCards as $agencyCard)
        <x-discovery-agency-card :card="$agencyCard" />
    @endforeach
</div>
<div data-discovery-no-results role="status" aria-live="polite" class="{{ $hasActiveSearch && count($agencyCards) === 0 ? 'grid' : 'hidden' }} justify-items-center gap-2 rounded-none border border-dashed border-[var(--account-border-strong)] bg-[var(--account-surface)] px-5 py-9 text-center">
    <p data-discovery-no-results-message class="font-bold text-[var(--account-text)]">{{ $hasLocationSearch ? __('messages.agency_discovery.no_location_results') : __('messages.agency_discovery.no_results') }}</p>
    <p class="text-sm text-[var(--account-text-secondary)]">{{ __('messages.agency_discovery.try_broader') }}</p>
</div>
@if ($results?->hasPages())
    <div data-discovery-pagination class="pt-2 [&_a]:rounded-none [&_button]:rounded-none">{{ $results->onEachSide(1)->links() }}</div>
@endif
