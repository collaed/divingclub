<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Jobs\SendMedicalCertificateApprovedEmail;
use App\Jobs\SendMedicalCertificateRejectedEmail;
use App\Models\Document;
use App\Models\MedicalReviewComment;
use App\Services\MedicalComplianceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Actionable worklist for medical certificate review: every member with a
 * current, not-yet-reviewed medical certificate, the certificate it
 * supersedes (if any) for comparison, and the three review actions.
 */
class MedicalReviewController extends Controller
{
    use PaginatesFromRequest;

    public function index(): View
    {
        $pending = Document::where('category', 'medical')
            ->where('is_current', true)
            ->whereNull('verified_at')
            ->whereNull('rejected_at')
            ->with(['user.detail', 'supersededBy'])
            ->orderBy('created_at')
            ->paginate($this->perPage(20));

        $presets = MedicalReviewComment::orderBy('sort_order')->get();

        return view('admin.medical-review.index', compact('pending', 'presets'));
    }

    public function validateCert(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->category === 'medical' && $document->isPendingReview(), 404);

        $v = $request->validate(['comment' => 'nullable|string|max:2000', 'date_established' => 'nullable|date']);
        $comment = trim((string) ($v['comment'] ?? '')) ?: null;

        $document->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'review_comment' => $comment,
            // The member typed this at upload time and nothing catches a typo
            // before it lands here — e.g. "01/01/2026" when the exam was
            // really done the day of upload — so the reviewer can fix it as
            // part of validating, same field they're already looking at.
            // Never cleared to null: an accidentally-blank submit must not
            // wipe out a date that was already correct.
            ...(! empty($v['date_established']) ? ['date_established' => $v['date_established']] : []),
        ]);

        app(MedicalComplianceService::class)->evaluateCertificate($document);

        SendMedicalCertificateApprovedEmail::dispatch($document->id)->afterCommit();

        return back()->with('success', $comment ? __('Certificate validated with a comment.') : __('Certificate validated.'));
    }

    public function storeComment(Request $request): RedirectResponse
    {
        $v = $request->validate(['text' => 'required|string|max:500']);
        MedicalReviewComment::create($v + ['sort_order' => MedicalReviewComment::max('sort_order') + 1]);

        return back()->with('success', __('Predefined comment added.'));
    }

    public function destroyComment(MedicalReviewComment $medicalReviewComment): RedirectResponse
    {
        $medicalReviewComment->delete();

        return back()->with('success', __('Predefined comment removed.'));
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->category === 'medical' && $document->isPendingReview(), 404);

        $v = $request->validate(['comment' => 'required|string|max:2000', 'date_established' => 'nullable|date']);

        $document->update([
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'review_comment' => $v['comment'],
            ...(! empty($v['date_established']) ? ['date_established' => $v['date_established']] : []),
        ]);

        SendMedicalCertificateRejectedEmail::dispatch($document->id)->afterCommit();

        return back()->with('success', __('Certificate rejected — the member has been emailed.'));
    }
}
