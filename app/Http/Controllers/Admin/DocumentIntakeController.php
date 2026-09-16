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
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('document-intake/uploads', 'local');

            $intake = DocumentIntake::create([
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'status' => 'processing',
                'uploaded_by' => auth()->id(),
            ]);

            ClassifyDocumentIntake::dispatch($intake->id)->afterCommit();
        }

        return back()->with('success', __(':count file(s) queued for classification.', ['count' => count($request->file('files'))]));
    }

    public function reclassify(Request $request, DocumentIntake $intake): RedirectResponse
    {
        $v = $request->validate([
            'type' => 'required|in:licence_scan,bank_statement',
            'federation_id' => 'required_if:type,licence_scan|nullable|exists:federations,id',
        ]);

        $intake->update(['status' => 'processing', 'error' => null]);

        ClassifyDocumentIntake::dispatch($intake->id, $v['type'], $v['federation_id'] ?? null)->afterCommit();

        return back()->with('success', __('Reclassifying — refresh in a moment.'));
    }
}
