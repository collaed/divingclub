<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Federation;
use App\Models\MedicalComplianceRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class MedicalExportController extends Controller
{
    /**
     * Export member list as CSV for federation medical submission.
     */
    public function exportList(Request $request)
    {
        $federationId = $request->get('federation_id');

        $query = User::with(['detail', 'documents' => fn ($q) => $q->where('category', 'medical')->where('is_current', true)])
            ->whereHas('detail')
            ->whereHas('role', fn ($q) => $q->whereNotIn('slug', ['pending']))
            ->orderBy('id');

        if ($federationId) {
            $query->whereHas('licences', fn ($q) => $q->where('federation_id', $federationId));
        }

        $members = $query->get();
        $fedName = $federationId ? Federation::find($federationId)?->acronym : 'all';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=medical-' . $fedName . '-' . date('Y-m-d') . '.csv',
        ];

        $callback = function () use ($members) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Date Demande', 'NOM', 'Prénom', 'Date de naissance', 'sexe',
                'n°, Rue', 'Pays', 'CP', 'Localité', 'Date Examen Médical',
            ], ';');

            foreach ($members as $member) {
                $d = $member->detail;
                $medCert = $member->documents->first();

                fputcsv($out, [
                    '',
                    strtoupper($d->last_name ?? ''),
                    $d->first_name ?? '',
                    $d->date_of_birth?->format('d/m/Y') ?? '',
                    $d->sex ?? '',
                    $d->address_line1 ?? '',
                    $d->country ?? '',
                    $d->postal_code ?? '',
                    $d->city ?? '',
                    $medCert?->date_established?->format('d/m/Y') ?? '',
                ], ';');
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download current medical certificates as a ZIP.
     * Files named: LASTNAME Firstname member# type.ext
     */
    public function downloadCertificates(Request $request)
    {
        $federationId = $request->get('federation_id');

        $query = Document::where('category', 'medical')
            ->where('is_current', true)
            ->with('user.detail');

        if ($federationId) {
            $query->whereHas('user.licences', fn ($q) => $q->where('federation_id', $federationId));
        }

        $docs = $query->get();

        if ($docs->isEmpty()) {
            return back()->with('error', __('No medical certificates to export.'));
        }

        $fedName = $federationId ? Federation::find($federationId)?->acronym : 'all';
        $zipPath = storage_path('app/temp/medical-certs-' . $fedName . '-' . date('Y-m-d') . '.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create ZIP file.');
        }

        foreach ($docs as $doc) {
            $disk = Storage::disk('local');
            if (! $disk->exists($doc->file_path)) {
                continue;
            }

            $d = $doc->user?->detail;
            $lastName = strtoupper(Str::ascii($d->last_name ?? 'UNKNOWN'));
            $firstName = Str::ascii($d->first_name ?? 'Unknown');
            $memberId = $doc->user_id;
            $type = $doc->cert_type ? strtoupper($doc->cert_type) : 'MED';
            $ext = pathinfo($doc->original_filename, PATHINFO_EXTENSION) ?: 'pdf';

            $filename = "{$lastName} {$firstName} {$memberId} {$type}.{$ext}";
            $zip->addFromString($filename, $disk->get($doc->file_path));
        }

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);
            return back()->with('error', __('No certificate files found on disk to export.'));
        }

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend();
    }
}
