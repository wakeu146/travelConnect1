<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyDocument;
use App\Services\AdminAgencyManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAgencyModerationController extends Controller
{
    public function verification(
        Request $request,
        Agency $agency,
        AdminAgencyManagementService $agencyManagement,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'queue' => ['nullable', Rule::in(['verification_pending', 'approved', 'rejected'])],
        ]);

        $emailSent = $agencyManagement->decideVerification(
            $agency,
            $request->user(),
            $data['decision'],
            $data['reason'] ?? null,
        );

        $message = $data['decision'] === 'approve'
            ? __('messages.admin_agencies.approved')
            : __('messages.admin_agencies.rejected');

        $response = redirect()->route('admin.agencies.show', [
            'agency' => $agency->id,
            'lang' => app()->getLocale(),
            'queue' => isset($data['queue'])
                ? ($data['decision'] === 'approve' ? 'approved' : 'rejected')
                : null,
        ])->with('status', $message);

        return $this->withEmailWarning($response, $emailSent);
    }

    public function suspend(
        Request $request,
        Agency $agency,
        AdminAgencyManagementService $agencyManagement,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $emailSent = $agencyManagement->suspend(
            $agency,
            $request->user(),
            $data['reason'] ?? null,
        );

        $response = redirect()->route('admin.agencies.show', [
            'agency' => $agency->id,
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_agencies.suspended'));

        return $this->withEmailWarning($response, $emailSent);
    }

    public function reinstate(
        Request $request,
        Agency $agency,
        AdminAgencyManagementService $agencyManagement,
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'min:10', 'max:2000'],
        ]);

        $agencyManagement->reinstate($agency, $request->user(), $data['reason'] ?? null);

        return redirect()->route('admin.agencies.show', [
            'agency' => $agency->id,
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_agencies.reinstated'));
    }

    public function destroy(
        Request $request,
        Agency $agency,
        AdminAgencyManagementService $agencyManagement,
    ): RedirectResponse {
        $agencyName = $agency->company_name;
        $emailSent = $agencyManagement->permanentlyDelete($agency);

        $response = redirect()->route('admin.dashboard.page', [
            'page' => 'all_agencies',
            'lang' => app()->getLocale(),
        ])->with('status', __('messages.admin_agencies.permanently_deleted', ['agency' => $agencyName]));

        return $this->withEmailWarning($response, $emailSent ?? true);
    }

    public function document(Agency $agency, AgencyDocument $document): StreamedResponse
    {
        abort_unless($document->agency_id === $agency->id, 404);
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->download($document->file_path);
    }

    private function withEmailWarning(RedirectResponse $response, bool $emailSent): RedirectResponse
    {
        if (! $emailSent) {
            $response->with('warning', __('messages.agency_moderation_email_failed'));
        }

        return $response;
    }
}
