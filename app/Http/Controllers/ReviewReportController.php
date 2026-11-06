<?php

namespace App\Http\Controllers;

use App\Enums\ReviewStatus;
use App\Models\ContentReport;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewReportController extends Controller
{
    public function store(Request $request, Review $review): RedirectResponse
    {
        abort_unless($request->user()->isTraveler(), 403);
        abort_unless($review->status === ReviewStatus::PUBLISHED && ! $review->is_hidden, 404);
        abort_unless($review->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1500',
                Rule::unique('content_reports', 'reporter_id')
                    ->where(fn ($query) => $query->where('review_id', $review->id)),
            ],
            'evidence' => ['nullable', 'string', 'max:2000'],
        ]);

        ContentReport::query()->create([
            'review_id' => $review->id,
            'reporter_id' => $request->user()->id,
            'reason' => $data['reason'],
            'evidence' => $data['evidence'] ?? null,
        ]);

        return back()->with('status', __('messages.trust_safety.report_submitted'));
    }
}
