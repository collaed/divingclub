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
        $url = env('OLD_SYNC_URL', 'https://clubcep.eu/wrapp/api_members.php');
        $key = env('OLD_SYNC_KEY', 'cep-sync-2026-hetzner');

        // Replace api_sync.php with api_members.php in URL
        $url = str_replace('api_sync.php', 'api_members.php', $url);

        $this->command->info("Fetching members from {$url}");

        $response = Http::timeout(60)->withHeaders(['X-Sync-Key' => $key])->get($url);
        if (! $response->ok()) {
            $this->command->error("HTTP {$response->status()}");

            return;
        }

        $data = $response->json();
        $this->command->info("Got {$data['count']} members from Joomla");

        $ffessm = Federation::where('acronym', 'FFESSM')->first();
        $updated = 0;
        $licencesAdded = 0;

        foreach ($data['members'] as $jm) {
            $email = $jm['email'] ?? '';
            $name = $jm['name'] ?? '';
            if (! $email || ! $name) {
                continue;
            }

            // Find user by email or name
            $user = User::where('primary_email', $email)->first();
            if (! $user) {
                $parts = preg_split('/\s+/', trim($name), 2);
                $first = $parts[0] ?? '';
                $last = $parts[1] ?? '';
                $user = User::whereHas('detail', function ($q) use ($first, $last) {
                    $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower($first)])
                        ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($last)]);
                })->first();
            }
            if (! $user) {
                continue;
            }

            $d = $user->detail;
            if (! $d) {
                continue;
            }

            // Enrich address/phone/DOB if empty
            $changes = [];
            if (! $d->address_line1 && ($jm['cb_adresse'] ?? '')) {
                $changes['address_line1'] = $jm['cb_adresse'];
            }
            if (! $d->postal_code && ($jm['cb_codepostal'] ?? '')) {
                $changes['postal_code'] = $jm['cb_codepostal'];
            }
            if (! $d->city && ($jm['cb_ville'] ?? '')) {
                $changes['city'] = $jm['cb_ville'];
            }
            if (! $d->country && ($jm['cb_pays'] ?? '')) {
                $changes['country'] = $jm['cb_pays'];
            }
            if (! $d->phone_mobile && ($jm['cb_portable'] ?? '')) {
                $changes['phone_mobile'] = $jm['cb_portable'];
            }
            if (! $d->phone_private && ($jm['cb_telephone'] ?? '')) {
                $changes['phone_private'] = $jm['cb_telephone'];
            }
            if (! $d->nationality && ($jm['cb_nationalite'] ?? '')) {
                $changes['nationality'] = $jm['cb_nationalite'];
            }
            if (! $d->date_of_birth && ($jm['cb_datenaissance'] ?? '') && $jm['cb_datenaissance'] !== '0000-00-00') {
                $changes['date_of_birth'] = $jm['cb_datenaissance'];
            }

            if (! empty($changes)) {
                $d->update($changes);
                $updated++;
            }

            // Add FFESSM licence if not exists and Joomla has one
            $certLevel = $jm['cb_niveauffessm'] ?? '';
            $licenceNo = $jm['cb_nolicence'] ?? '';
            if ($ffessm && $certLevel && ! $user->licences()->where('federation_id', $ffessm->id)->exists()) {
                MemberLicence::create([
                    'user_id' => $user->id,
                    'federation_id' => $ffessm->id,
                    'licence_number' => $licenceNo ?: null,
                    'season' => '2025-2026',
                ]);
                $licencesAdded++;
            }

            // Add certification level if not exists
            if ($certLevel && $user->certificationLevels->isEmpty()) {
                $cert = CertificationLevel::where('code', $certLevel)
                    ->orWhere('name', 'LIKE', "%{$certLevel}%")
                    ->first();
                if ($cert) {
                    $user->certificationLevels()->syncWithoutDetaching([$cert->id => ['is_primary' => true]]);
                }
            }
        }

        $this->command->info("Updated {$updated} member profiles, added {$licencesAdded} licences");

        // Import scancards
        $this->importScancards();
    }

    private function importScancards(): void
    {
        $srcDir = env('SCANCARDS_PATH', '/home/collaed/tmp/domains/clubcep.eu/public_html/scancards');
        if (! is_dir($srcDir)) {
            $this->command->warn("Scancards not found at {$srcDir}");

            return;
        }

        $files = scandir($srcDir);
        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $fullPath = "{$srcDir}/{$file}";
            if (! is_file($fullPath)) {
                continue;
            }

            // Parse filename: "Firstname LASTNAME_joomlaID_timestamp_doctype.ext"
            if (preg_match('/^(.+)_(\d+)_(\d+)_(\d+)\.(pdf|jpg|jpeg|png|gif)$/i', $file, $m)) {
                $memberName = $m[1];
                $joomlaId = $m[2];
                $docType = $m[4]; // 1=medical, 2=licence, 3=other
            } elseif (str_starts_with($file, '__')) {
                $skipped++;

                continue; // unnamed files
            } else {
                $skipped++;

                continue;
            }

            // Find user by name
            $parts = preg_split('/\s+/', trim($memberName), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';

            $user = User::whereHas('detail', function ($q) use ($first, $last) {
                $q->whereRaw('LOWER(first_name) = ?', [mb_strtolower($first)])
                    ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($last)]);
            })->first();

            if (! $user) {
                $skipped++;

                continue;
            }

            // Skip if already imported
            $storagePath = "private/scancards/{$file}";
            if (Document::where('file_path', $storagePath)->exists()) {
                $skipped++;

                continue;
            }

            // Copy file
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
                'is_current' => false, // don't override existing current certs
            ]);
            $imported++;
        }

        $this->command->info("Scancards: {$imported} imported, {$skipped} skipped");
    }
}
