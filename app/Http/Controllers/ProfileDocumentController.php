<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Services\MedicalComplianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProfileDocumentController extends Controller
{
    public function upload(Request $request, ?User $user = null)
    {
        $viewer = auth()->user();
        $target = $user ?? ($request->target_user_id ? User::findOrFail($request->target_user_id) : $viewer);

        if ($target->id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
            'category' => 'required|string|in:certification,medical,insurance,other',
            'date_established' => 'nullable|date',
            'cert_type' => 'nullable|string|max:30',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$target->id, 'local');

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

            $memberName = $target->detail?->first_name.' '.$target->detail?->last_name;
            $bureauEmails = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['bureau_master', 'bureau_technical']))
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

        return back()->with('success', __('Document uploaded.'));
    }

    public function download(Document $document)
    {
        $viewer = auth()->user();
        if ($document->user_id !== $viewer->id && ! $viewer->isBureauMaster()) {
            abort(403);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    public function verify(Request $request, Document $document)
    {
        abort_unless(auth()->user()->isBureauMaster(), 403);

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

        return back()->with('success', __('Certificate verified.'));
    }
}
