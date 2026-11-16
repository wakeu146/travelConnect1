@foreach ($agencies as $agency)
    <x-agency-card
        :name="$agency->company_name"
        :saved="in_array((int) $agency->id, $savedAgencyIds, true)"
        :countries="$agency->countries->pluck('name')->all()"
        :location="$agency->address"
        :image="$agency->logoUrl() ?? $agency->coverImageUrl()"
        :alt="$agency->company_name"
        :description="$agency->description"
        :service="$agency->services->first()?->name"
        :rating="$agency->published_reviews_count > 0 ? number_format((float) $agency->published_rating, 1) : '—'"
        :review-count="$agency->published_reviews_count"
        :trust-score="$agency->trust_score"
        use-real-image="true"
    />
@endforeach
