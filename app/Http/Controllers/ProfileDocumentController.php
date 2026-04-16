<?php

namespace App\Http\Controllers;

use App\Jobs\OcrMedicalCert;
use App\Models\Document;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileDocumentController extends Controller
{
    public function upload(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
            'category' => 'required|string|in:certification,medical,insurance,other',
            'date_established' => 'nullable|date',
            'cert_type' => 'nullable|string|max:30',
        ]);

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension() ?: 'pdf';
        $detail = $target->detail;
        $datePart = $request->date_established ? date('Y-m-d', strtotime($request->date_established)) : date('Y-m-d');
        $storedName = Str::slug($detail?->last_name.' '.$detail?->first_name.' '.($request->cert_type ?? $request->category).' '.$datePart).'.'.$ext;
        $path = $file->storeAs('documents/'.$target->id, $storedName, 'local');

        $doc = Document::create([
            'user_id' => $target->id,
            'category' => $request->category,
            'cert_type' => $request->cert_type,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'date_established' => $request->date_established,
            'is_current' => true,
        ]);

        if ($request->category === 'medical') {
            app(MedicalComplianceService::class)->evaluateCertificate($doc);

            // OCR the cert in background to detect establishment date
            if (! $doc->date_established) {
                OcrMedicalCert::dispatch($doc->id);
            }

            $memberName = $target->detail?->first_name.' '.$target->detail?->last_name;
            $bureauEmails = User::role(['bureau_master', 'bureau_technical'])
                ->pluck('primary_email');

            Mail::raw(
                __(':name uploaded a medical certificate (:type, :date).', [
                    'name' => $memberName,
                    'type' => $doc->cert_type ?? 'medical',
                    'date' => $doc->date_established?->format('Y-m-d') ?? '—',
                ]),
                fn ($m) => $m->to($bureauEmails->all())->subject(__('Medical certificate uploaded'))
            );
        }

        return back()->with('success', __('Document uploaded.'))->withInput(['tab' => $request->category === 'medical' ? 'medical' : 'info']);
    }

    public function download(Document $document)
    {
        $viewer = auth()->user();
        if ($document->user_id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        // Try local disk first, then public (Joomla imports stored in public)
        if (Storage::disk('local')->exists($document->file_path)) {
            return Storage::disk('local')->download($document->file_path, $document->original_filename);
        }

        if (Storage::disk('public')->exists($document->file_path)) {
            return Storage::disk('public')->download($document->file_path, $document->original_filename);
        }

        abort(404, __('File not found.'));
    }

    public function view(Document $document)
    {
        $viewer = auth()->user();
        if ($document->user_id !== $viewer->id && ! $viewer->isBureau()) {
            abort(403);
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($document->file_path)) {
                return response(Storage::disk($disk)->get($document->file_path))
                    ->header('Content-Type', $document->mime_type ?? 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="'.$document->original_filename.'"');
            }
        }

        abort(404);
    }

    public function verify(Request $request, Document $document)
    {
        abort_unless(auth()->user()->isBureau(), 403);

        $data = ['is_verified' => true, 'verified_by' => auth()->id(), 'verified_at' => now()];

        if ($request->filled('date_established')) {
            $data['date_established'] = $request->date('date_established');
        }

        if ($request->filled('cert_type')) {
            $data['cert_type'] = $request->input('cert_type');
        }

        $document->update($data);

        if ($document->category === 'medical') {
            app(MedicalComplianceService::class)->evaluateCertificate($document);
        }

        return back()->with('success', __('Certificate verified.'))->withInput(['tab' => 'medical']);
    }
}
