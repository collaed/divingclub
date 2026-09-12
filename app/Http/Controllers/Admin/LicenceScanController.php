<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\PdfMetadata;
use App\Http\Controllers\Concerns\PaginatesFromRequest;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessLicenceScan;
use App\Models\Federation;
use App\Models\LicenceScan;
use App\Models\MemberLicence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bulk intake for federation licence scans: dump a batch of files, each is
 * rendered + read automatically (ProcessLicenceScan), and anything that
 * couldn't be assigned to exactly one member lands here for manual review.
 */
class LicenceScanController extends Controller
{
    use PaginatesFromRequest;

    public function index(): View
    {
        $needsReview = LicenceScan::where('status', 'needs_review')
            ->with(['federation', 'uploader'])
            ->latest()
            ->paginate($this->perPage(20));

        $federations = Federation::active()->orderBy('acronym')->get();
        $members = User::whereHas('detail')->with('detail')->get()
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name])
            ->sortBy('name')->values();

        return view('admin.licence-scans.index', compact('needsReview', 'federations', 'members'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'federation_id' => 'required|exists:federations,id',
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
        ]);

        foreach ($request->file('files') as $file) {
            $path = $file->store('licence-scans/uploads', 'local');

            $scan = LicenceScan::create([
                'federation_id' => $request->federation_id,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'status' => 'processing',
                'uploaded_by' => auth()->id(),
            ]);

            ProcessLicenceScan::dispatch($scan->id)->afterCommit();
        }

        return back()->with('success', __(':count file(s) queued for processing.', ['count' => count($request->file('files'))]));
    }

    public function assign(Request $request, LicenceScan $licenceScan): RedirectResponse
    {
        abort_unless($licenceScan->isPendingReview(), 404);

        $v = $request->validate([
            'user_id' => 'required|exists:users,id',
            'licence_number' => 'nullable|string|max:100',
            'year' => 'nullable|string|max:20',
        ]);

        MemberLicence::updateOrCreate(
            ['user_id' => $v['user_id'], 'federation_id' => $licenceScan->federation_id],
            [
                'licence_number' => $v['licence_number'] ?? null,
                'season' => $v['year'] ?? null,
                'scan_image_path' => $licenceScan->image_path,
                'card_issued_at' => $this->extractCardIssuedAt($licenceScan),
            ],
        );

        $licenceScan->update([
            'status' => 'applied',
            'matched_user_id' => $v['user_id'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Licence assigned.'));
    }

    public function image(LicenceScan $licenceScan): Response
    {
        abort_unless($licenceScan->image_path && Storage::disk('local')->exists($licenceScan->image_path), 404);

        return response(Storage::disk('local')->get($licenceScan->image_path))->header('Content-Type', 'image/png');
    }

    public function destroy(LicenceScan $licenceScan): RedirectResponse
    {
        abort_unless($licenceScan->isPendingReview() || $licenceScan->status === 'failed', 404);

        if ($licenceScan->file_path) {
            Storage::disk('local')->delete($licenceScan->file_path);
        }
        if ($licenceScan->image_path) {
            Storage::disk('local')->delete($licenceScan->image_path);
        }
        $licenceScan->update(['status' => 'discarded', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);

        return back()->with('success', __('Scan discarded.'));
    }

    /**
     * The card's issuance date, read from the PDF's own CreationDate — only
     * trusted for FLASSA (see PdfMetadata), and only meaningful for a PDF
     * upload, not a photographed scan.
     */
    private function extractCardIssuedAt(LicenceScan $licenceScan): ?Carbon
    {
        if ($licenceScan->federation->acronym !== 'FLASSA' || ! Storage::disk('local')->exists($licenceScan->file_path)) {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($licenceScan->file_path);
        if (! str_contains(mime_content_type($absolutePath) ?: '', 'pdf')) {
            return null;
        }

        return PdfMetadata::creationDate($absolutePath);
    }
}
