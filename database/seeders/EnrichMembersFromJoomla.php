<?php

namespace Database\Seeders;

use App\Models\CertificationLevel;
use App\Models\Document;
use App\Models\Federation;
use App\Models\MemberLicence;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class EnrichMembersFromJoomla extends Seeder
{
    public function run(): void
    {
        $url = str_replace('api_sync.php', 'api_members.php', env('OLD_SYNC_URL', 'https://clubcep.eu/wrapp/api_members.php'));
        $key = env('OLD_SYNC_KEY', 'cep-sync-2026-hetzner');

        $this->command->info("Fetching from {$url}");
        $response = Http::timeout(60)->withHeaders(['X-Sync-Key' => $key])->get($url);
        if (! $response->ok()) {
            $this->command->error("HTTP {$response->status()}: {$response->body()}");

            return;
        }

        $data = $response->json();
        $this->command->info("Got {$data['count']} members");

        $ffessm = Federation::where('acronym', 'FFESSM')->first();
        $updated = 0;
        $licencesAdded = 0;

        foreach ($data['members'] as $jm) {
            $user = User::where('primary_email', $jm['email'] ?? '')->first();
            if (! $user || ! $user->detail) {
                continue;
            }

            $d = $user->detail;
            $changes = [];

            // Address
            if (! $d->address_line1 && ($jm['cb_addpriv'] ?? '')) {
                $changes['address_line1'] = $jm['cb_addpriv'];
            }
            if (! $d->postal_code && ($jm['cb_codepostal'] ?? '')) {
                $changes['postal_code'] = $jm['cb_codepostal'];
            }
            if (! $d->city && ($jm['cb_ville'] ?? '')) {
                $changes['city'] = $jm['cb_ville'];
            }
            if (! $d->country) {
                $changes['country'] = $jm['cb_pays'] ?? $jm['cb_country'] ?? '';
            }

            // Phones
            if (! $d->phone_mobile && ($jm['cb_telgsm'] ?? '')) {
                $changes['phone_mobile'] = $jm['cb_telgsm'];
            }
            if (! $d->phone_office && ($jm['cb_teloff'] ?? '')) {
                $changes['phone_office'] = $jm['cb_teloff'];
            }
            if (! $d->phone_private && ($jm['cb_telpri'] ?? '')) {
                $changes['phone_private'] = $jm['cb_telpri'];
            }

            // Personal
            if (! $d->nationality && ($jm['cb_nationalite'] ?? '')) {
                $changes['nationality'] = $jm['cb_nationalite'];
            }
            if (! $d->date_of_birth && ($jm['cb_datenaissance'] ?? '') && $jm['cb_datenaissance'] !== '0000-00-00') {
                $changes['date_of_birth'] = $jm['cb_datenaissance'];
            }
            if (! $d->sex && ($jm['cb_sexe'] ?? '')) {
                $changes['sex'] = str_contains(strtolower($jm['cb_sexe']), 'omme') ? 'M' : 'F';
            }
            if (! $d->place_of_birth && ($jm['cb_lieunaiss'] ?? '')) {
                $changes['place_of_birth'] = $jm['cb_lieunaiss'];
            }

            // Emergency contact: "Name +phone" or "Name phone"
            if (! $d->emergency_contact_name && ($jm['cb_peracc'] ?? '')) {
                $ec = $jm['cb_peracc'];
                if (preg_match('/^(.+?)\s*([+\d][\d\s]{6,})$/', $ec, $ecm)) {
                    $changes['emergency_contact_name'] = trim($ecm[1]);
                    $changes['emergency_contact_phone'] = trim($ecm[2]);
                } else {
                    $changes['emergency_contact_name'] = $ec;
                }
            }

            if (! empty($changes)) {
                $d->update($changes);
                $updated++;
            }

            // FFESSM licence
            $licenceNo = $jm['cb_nolicence'] ?? '';
            if ($ffessm && $licenceNo && ! $user->licences()->where('federation_id', $ffessm->id)->exists()) {
                MemberLicence::create([
                    'user_id' => $user->id,
                    'federation_id' => $ffessm->id,
                    'licence_number' => $licenceNo,
                    'season' => '2025-2026',
                ]);
                $licencesAdded++;
            }

            // Certification level
            $certLevel = $jm['cb_niveauffessm'] ?? '';
            if ($certLevel && $user->certificationLevels->isEmpty()) {
                $cert = CertificationLevel::where('code', $certLevel)
                    ->orWhere('name', 'LIKE', "%{$certLevel}%")
                    ->first();
                if ($cert) {
                    $user->certificationLevels()->syncWithoutDetaching([$cert->id => ['is_primary' => true]]);
                }
            }
        }

        $this->command->info("Enriched {$updated} profiles, added {$licencesAdded} licences");
        $this->importScancards();
    }

    private function importScancards(): void
    {
        $srcDir = env('SCANCARDS_PATH', '/home/collaed/tmp/domains/clubcep.eu/public_html/scancards');
        if (! is_dir($srcDir)) {
            $this->command->warn("Scancards not found: {$srcDir}");

            return;
        }

        $imported = 0;
        $skipped = 0;

        foreach (scandir($srcDir) as $file) {
            if ($file === '.' || $file === '..' || str_starts_with($file, '__')) {
                continue;
            }

            $fullPath = "{$srcDir}/{$file}";
            if (! is_file($fullPath)) {
                continue;
            }

            // Parse: "Firstname LASTNAME_joomlaID_timestamp_doctype.ext"
            if (! preg_match('/^(.+)_(\d+)_(\d+)_(\d+)\.\w+$/i', $file, $m)) {
                $skipped++;

                continue;
            }

            $memberName = $m[1];
            $docType = $m[4];
            $parts = preg_split('/\s+/', trim($memberName), 2);

            $user = User::whereHas('detail', function ($q) use ($parts) {
                $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower($parts[0] ?? '')])
                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($parts[1] ?? '')]);
            })->first();

            if (! $user) {
                $skipped++;

                continue;
            }

            $storagePath = "private/scancards/{$file}";
            if (Document::where('file_path', $storagePath)->exists()) {
                $skipped++;

                continue;
            }

            $destDir = storage_path('app/private/scancards');
            if (! is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            copy($fullPath, storage_path("app/{$storagePath}"));

            $category = $docType === '1' ? 'medical' : ($docType === '2' ? 'insurance' : 'other');

            Document::create([
                'user_id' => $user->id,
                'category' => $category,
                'file_path' => $storagePath,
                'original_filename' => $file,
                'mime_type' => mime_content_type($fullPath) ?: 'application/octet-stream',
                'size_bytes' => filesize($fullPath),
                'is_current' => false,
            ]);
            $imported++;
        }

        $this->command->info("Scancards: {$imported} imported, {$skipped} skipped");
    }
}
