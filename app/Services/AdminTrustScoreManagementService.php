<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\VerificationStatus;
use App\Models\Agency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AdminTrustScoreManagementService
{
    /**
     * @return array{agencies: LengthAwarePaginator<int, Agency>}
     */
    public function pageData(string $page, ?string $search): array
    {
        return [
            'agencies' => $page === 'trust_score_overview'
                ? $this->trustScoreOverview($search)
                : $this->agenciesRequiringAttention($search),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Agency>
     */
    private function trustScoreOverview(?string $search): LengthAwarePaginator
    {
        $query = Agency::query()
            ->with('latestTrustScoreLog')
            ->withCount([
                'reviews as published_review_count' => fn (Builder $reviews) => $reviews
                    ->where('status', ReviewStatus::PUBLISHED->value)
                    ->where('is_hidden', false),
            ])
            ->orderByDesc('trust_score')
            ->orderBy('company_name')
            ->orderBy('id');

        $this->applySearch($query, $search);

        return $query->paginate(20)->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, Agency>
     */
    private function agenciesRequiringAttention(?string $search): LengthAwarePaginator
    {
        $query = Agency::query()
            ->with('latestTrustScoreLog')
            ->withCount([
                'reviews as flagged_reviews_count' => fn (Builder $reviews) => $reviews
                    ->where('status', ReviewStatus::FLAGGED->value),
                'reviews as hidden_reviews_count' => fn (Builder $reviews) => $reviews
                    ->where('is_hidden', true),
                'contentReports as active_reports_count' => fn (Builder $reports) => $reports
                    ->whereIn('content_reports.status', ['open', 'investigating']),
                'travelerIssues as active_complaints_count' => fn (Builder $issues) => $issues
                    ->whereIn('traveler_issues.status', ['open', 'in_review']),
            ])
            ->where(function (Builder $agencies): void {
                $agencies
                    ->where('verification_status', '!=', VerificationStatus::VERIFIED->value)
                    ->orWhere('operating_status', 'suspended')
                    ->orWhereHas('reviews', fn (Builder $reviews) => $reviews
                        ->where('status', ReviewStatus::FLAGGED->value)
                        ->orWhere('is_hidden', true))
                    ->orWhereHas('contentReports', fn (Builder $reports) => $reports
                        ->whereIn('content_reports.status', ['open', 'investigating']))
                    ->orWhereHas('travelerIssues', fn (Builder $issues) => $issues
                        ->whereIn('traveler_issues.status', ['open', 'in_review']));
            })
            ->orderByDesc('updated_at')
            ->orderBy('company_name')
            ->orderBy('id');

        $this->applySearch($query, $search);

        return $query->paginate(20)->withQueryString();
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        if (filled($search)) {
            $query->where('company_name', 'like', '%'.$search.'%');
        }
    }
}
