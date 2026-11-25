<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Jobs\ClassifyDocumentIntake;
use App\Models\DocumentIntake;
use App\Models\Federation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared upload point for the two OCR-driven bureau intake pipelines
 * (federation licence scans, bank statements) — a document dropped here is
 * classified automatically and routed to whichever existing screen/queue
 * handles that type. Neither pipeline's own review flow changes; this is
 * only the entry point and a record of what was assumed.
 */
class DocumentIntakeController extends Controller
{
    use PaginatesFromRequest;

    public function index(): View
    {
        $intakes = DocumentIntake::with(['federation', 'licenceScan', 'uploader'])
            ->latest()
            ->paginate($this->perPage(20));

        $federations = Federation::active()->orderBy('acronym')->get();

        return view('admin.document-intake.index', compact('intakes', 'federations'));
    }

    public function store(Request $request): RedirectResponse
    {
        // The generic zone sends no intake_type and keeps today's pdf/image
        // auto-classify behavior unchanged; the dedicated licence-scan and
        // FFESSM-brevet zones each declare their own type up front, so the
        // mimetypes accepted narrow to whatever that zone actually sends.
        $request->validate([
            'intake_type' => 'nullable|in:licence_scan,bank_statement,ffessm_brevet',
            'federation_id' => 'nullable|exists:federations,id',
            'files' => 'required|array|min:1',
            // 5MB is generous for a scanned document and keeps the limit under
            // SonarCloud's 8MB permissive-upload-size threshold (php:S5693).
            'files.*' => match ($request->input('intake_type')) {
                'ffessm_brevet' => 'file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel|max:5120',
                'licence_scan' => 'file|mimetypes:image/jpeg,image/png|max:5120',
                // The bank statement zone accepts the bank's own PDF export
                // (OCR'd/text-parsed) or its XLSX account-history export
                // (parsed directly, no OCR — see BankReconciliationService::parseXlsxStatement()).
                'bank_statement' => 'file|mimetypes:application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel|max:5120',
                default => 'file|mimetypes:application/pdf,image/jpeg,image/png|max:5120',
            },
        ]);

        $intakeType = $request->input('intake_type');
        $federationId = $intakeType === 'licence_scan' && $request->filled('federation_id') ? (int) $request->input('federation_id') : null;

        foreach ($request->file('files') as $file) {
            $path = $file->store('document-intake/uploads', 'local');

            $intake = DocumentIntake::create([
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'status' => 'processing',
                'uploaded_by' => auth()->id(),
            ]);

            ClassifyDocumentIntake::dispatch($intake->id, $intakeType, $federationId)->afterCommit();
        }

        return back()->with('success', __(':count file(s) queued for classification.', ['count' => count($request->file('files'))]));
    }

    public function reclassify(Request $request, DocumentIntake $intake): RedirectResponse
    {
        $v = $request->validate([
            'type' => 'required|in:licence_scan,bank_statement,ffessm_brevet',
            'federation_id' => 'required_if:type,licence_scan|nullable|exists:federations,id',
        ]);

        $intake->update(['status' => 'processing', 'error' => null]);

        ClassifyDocumentIntake::dispatch($intake->id, $v['type'], $v['federation_id'] ?? null)->afterCommit();

        return back()->with('success', __('Reclassifying — refresh in a moment.'));
    }
}
