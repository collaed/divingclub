<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Federation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class MedicalExportController extends Controller
{
    /**
     * Export member list as CSV for federation medical submission.
     */
    public function exportList(Request $request): Response
    {
        $federationId = $request->get('federation_id');

        $query = User::with(['detail', 'documents' => fn ($q) => $q->where('category', 'medical')->where('is_current', true)])
            ->whereHas('detail')
            ->role(['member', 'instructor', 'bureau_finance', 'bureau_technical', 'bureau_master'])
            ->orderBy('id');

        if ($federationId) {
            $query->whereHas('licences', fn ($q) => $q->where('federation_id', $federationId));
        }

        $members = $query->get();
        $fedName = $federationId ? Federation::find($federationId)?->acronym : 'all';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=medical-'.$fedName.'-'.date('Y-m-d').'.csv',
        ];

        $callback = function () use ($members): void {
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
     * Download current medical certificates as a ZIP — only the active
     * (non-superseded) certificate per member, never older versions.
     * Includes DB-tracked documents + legacy files from private/medical/.
     * Files named: LASTNAME Firstname date.ext
     */
    public function downloadCertificates(Request $request): Response
    {
        $federationId = $request->get('federation_id');

        // DB-tracked documents — is_current already excludes superseded ones.
        $query = Document::where('category', 'medical')
            ->where('is_current', true)
            ->with('user.detail');

        if ($federationId) {
            $query->whereHas('user.licences', fn ($q) => $q->where('federation_id', $federationId));
        }

        $docs = $query->get();

        // Legacy files from private/medical/ (LASTNAME_Firstname.pdf) — these
        // predate is_current tracking, so a member can have several old scans
        // sitting there with nothing marking which one is still valid. Group
        // by (last name, first name) and keep only the most recently modified
        // file per person, so a superseded legacy scan never rides along.
        $legacyFiles = [];
        $legacyDir = storage_path('app/private/medical');
        if (is_dir($legacyDir)) {
            // If filtering by federation, get matching user names to filter legacy files
            $matchNames = null;
            if ($federationId) {
                $matchNames = User::whereHas('licences', fn ($q) => $q->where('federation_id', $federationId))
                    ->with('detail')
                    ->get()
                    ->map(fn ($u) => strtoupper(Str::ascii($u->detail?->last_name ?? '')))
                    ->filter()
                    ->toArray();
            }

            // Historic layout is flat (LASTNAME_Firstname.pdf); the imported
            // data on the servers nests one level (private/medical/<id>/*.pdf).
            // Only ever add real files — glob() also returns the sub-directories,
            // and ZipArchive::addFile() on a directory aborts the whole archive.
            $candidates = array_merge(
                glob("{$legacyDir}/*") ?: [],
                glob("{$legacyDir}/*/*") ?: [],
            );

            $newestPerPerson = [];
            foreach ($candidates as $file) {
                if (! is_file($file)) {
                    continue;
                }

                $basename = pathinfo($file, PATHINFO_FILENAME);
                // Match LASTNAME_Firstname or LASTNAME Firstname
                [$lastNamePart, $firstNamePart] = [...preg_split('/[_\s]+/', $basename, 2), ''];
                $lastName = strtoupper($lastNamePart);
                $firstName = strtoupper($firstNamePart);

                if ($matchNames !== null && ! in_array($lastName, $matchNames)) {
                    continue;
                }

                // Skip if we already have a DB document for this user
                $alreadyInDb = $docs->contains(function ($doc) use ($lastName, $firstName): bool {
                    $d = $doc->user?->detail;

                    return strtoupper(Str::ascii($d?->last_name ?? '')) === $lastName
                        && strtoupper(Str::ascii($d?->first_name ?? '')) === $firstName;
                });
                if ($alreadyInDb) {
                    continue;
                }

                $personKey = "{$lastName}|{$firstName}";
                $mtime = filemtime($file) ?: 0;
                if (! isset($newestPerPerson[$personKey]) || $mtime > $newestPerPerson[$personKey]['mtime']) {
                    $newestPerPerson[$personKey] = ['file' => $file, 'mtime' => $mtime];
                }
            }

            $legacyFiles = array_column($newestPerPerson, 'file');
        }

        if ($docs->isEmpty() && $legacyFiles === []) {
            return back()->with('error', __('No medical certificates to export.'));
        }

        $fedName = $federationId ? Federation::find($federationId)?->acronym : 'all';
        $zipPath = storage_path('app/temp/medical-certs-'.$fedName.'-'.date('Y-m-d').'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'Could not create ZIP file.');
        }

        // Add DB-tracked documents
        $usedNames = [];
        foreach ($docs as $doc) {
            $disk = Storage::disk('local');
            if (! $disk->exists($doc->file_path)) {
                continue;
            }

            $d = $doc->user?->detail;
            $lastName = strtoupper(Str::ascii($d->last_name ?? 'UNKNOWN'));
            $firstName = Str::ascii($d->first_name ?? 'Unknown');
            $date = ($doc->date_established ?? $doc->created_at)->format('Y-m-d');
            $ext = pathinfo($doc->original_filename, PATHINFO_EXTENSION) ?: 'pdf';

            $filename = "{$lastName} {$firstName} {$date}.{$ext}";
            // Two members sharing a name and exam date would otherwise collide.
            if (isset($usedNames[$filename])) {
                $filename = "{$lastName} {$firstName} {$date} ({$doc->user_id}).{$ext}";
            }
            $usedNames[$filename] = true;

            $zip->addFromString($filename, $disk->get($doc->file_path));
        }

        // Add legacy files — prefix with the containing folder when nested so
        // the member association isn't lost, and guard is_file() once more.
        foreach ($legacyFiles as $file) {
            if (! is_file($file)) {
                continue;
            }
            $parent = basename(dirname($file));
            $entry = $parent === 'medical' ? basename($file) : $parent.' '.basename($file);
            $zip->addFile($file, $entry);
        }

        $zip->close();

        if (! file_exists($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);

            return back()->with('error', __('No certificate files found on disk to export.'));
        }

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend();
    }
}
