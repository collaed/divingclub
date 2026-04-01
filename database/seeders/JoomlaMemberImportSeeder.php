<?php

namespace Database\Seeders;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Import real CEP members from Joomla backup (jos_users + jos_comprofiler).
 *
 * Prerequisites: load the Joomla SQL dump into a local MySQL database called `joomla_tmp`.
 * Run: php artisan db:seed --class=JoomlaMemberImportSeeder
 */
class JoomlaMemberImportSeeder extends Seeder
{
    /** Map Joomla cb_statut → DivingClub member_statuses.slug */
    private const STATUS_MAP = [
        'Membre de droit' => 'membre_de_droit',
        'Membre externe' => 'actif',
        'Membre associé' => 'famille',
        'Membre assimilé' => 'fonctionnaire',
    ];

    /** Map Joomla cb_niveauffessm → FFESSM certification_levels.code (primary cert) */
    private const CERT_MAP = [
        'PN1' => 'PE20', 'PN2' => 'PA20', 'PN3' => 'PE40',
        'N1' => 'N1', 'N2' => 'N2', 'N3' => 'N3', 'N4' => 'N4',
        'E1' => 'E1', 'E2' => 'E2', 'E3' => 'E3', 'E4' => 'E4',
        'Or' => 'N5', '-' => null,
    ];

    public function run(): void
    {
        $joomla = DB::connection('joomla');

        $members = $joomla->table('jos_users as u')
            ->join('jos_comprofiler as cb', 'cb.user_id', '=', 'u.id')
            ->where('u.block', 0)
            ->where('u.id', '!=', 62) // skip admin account
            ->orderBy('cb.lastname')
            ->get();

        $statuses = MemberStatus::pluck('id', 'slug');
        $ffessm = Federation::where('acronym', 'FFESSM')->first();
        $certLevels = CertificationLevel::where('federation_id', $ffessm->id)
            ->pluck('id', 'code');

        $imported = 0;

        foreach ($members as $m) {
            $statusSlug = self::STATUS_MAP[$m->cb_statut] ?? 'actif';
            $gender = match ($m->cb_sexe) {
                'Homme' => 'M', 'Femme' => 'F', default => null,
            };

            // Determine role
            $role = 'member';
            if ($m->cb_bureau) {
                $role = 'bureau_technical';
            } elseif ($m->cb_moniteur || $m->cb_inst) {
                $role = 'instructor';
            }

            $dob = ($m->cb_datenaissance && $m->cb_datenaissance !== '0000-00-00')
                ? $m->cb_datenaissance : null;

            // Find or create user
            $user = User::where('primary_email', $m->email)->first();

            if ($user) {
                // Update existing
                $user->update([
                    'status_id' => $statuses[$statusSlug] ?? $user->status_id,
                ]);
                $user->syncRoles([$role]);
            } else {
                $legacyRoleId = DB::table('legacy_roles')->where('slug', $role)->value('id')
                    ?? DB::table('legacy_roles')->where('slug', 'member')->value('id');

                $user = User::create([
                    'username' => Str::slug($m->firstname.' '.$m->lastname, '.'),
                    'primary_email' => $m->email,
                    'password' => Hash::make(Str::random(32)),
                    'role_id' => $legacyRoleId,
                    'status_id' => $statuses[$statusSlug] ?? $statuses['actif'],
                    'preferred_locale' => 'fr',
                    'email_verified_at' => now(),
                ]);
                $user->assignRole($role);
            }

            // Upsert member details
            $user->detail()->updateOrCreate([], [
                'first_name' => $m->firstname,
                'last_name' => $m->lastname,
                'birth_name' => $m->cb_nomdenaissance ?: null,
                'date_of_birth' => $dob,
                'sex' => $gender,
                'phone_mobile' => $m->cb_telgsm ?: null,
                'phone_private' => $m->cb_telpri ?: null,
                'address_line1' => $m->cb_addpriv ?: null,
                'country' => $m->cb_country ?: null,
                'emergency_contact_name' => $m->cb_famnam ?: null,
                'active_instructor' => (bool) ($m->cb_moniteur || $m->cb_inst),
                'bureau_member' => (bool) $m->cb_bureau,
            ]);

            // Federation licence (FFESSM)
            if ($m->cb_nolicence) {
                $user->licences()->updateOrCreate(
                    ['federation_id' => $ffessm->id],
                    ['licence_number' => $m->cb_nolicence, 'season' => '2025-2026']
                );
            }

            // Certification levels
            $certCodes = $this->parseCertification($m->cb_niveauffessm);
            if ($certCodes) {
                $sync = [];
                foreach ($certCodes as $i => $code) {
                    if (isset($certLevels[$code])) {
                        $sync[$certLevels[$code]] = ['is_primary' => $i === 0, 'display_priority' => $i];
                    }
                }
                if ($sync) {
                    $user->certificationLevels()->sync($sync);
                }
            }

            // Medical certificate
            if ($m->cb_datcertif && $m->cb_datcertif !== '0000-00-00') {
                $existing = $user->documents()->where('category', 'medical')->where('is_current', true)->first();
                if (! $existing) {
                    // Find the scanned file from VisitesMed
                    $certFile = $this->findMedicalCertFile($m->id);
                    $storedPath = '';
                    $mime = '';
                    $size = 0;
                    $originalName = 'imported_from_joomla';

                    if ($certFile) {
                        $ext = pathinfo($certFile, PATHINFO_EXTENSION);
                        $storedName = 'medical_'.Str::slug($m->firstname.'-'.$m->lastname).'-'.$m->id.'.'.$ext;
                        $storedPath = 'documents/'.$user->id.'/'.$storedName;
                        $sourcePath = $this->medicalDir().'/'.$certFile;

                        $destDir = storage_path('app/public/documents/'.$user->id);
                        if (! is_dir($destDir)) {
                            mkdir($destDir, 0755, true);
                        }
                        copy($sourcePath, $destDir.'/'.$storedName);

                        $mime = match (strtolower($ext)) {
                            'pdf' => 'application/pdf',
                            'jpg', 'jpeg' => 'image/jpeg',
                            'png' => 'image/png',
                            default => 'application/octet-stream',
                        };
                        $size = filesize($sourcePath);
                        $originalName = $certFile;
                    }

                    $user->documents()->create([
                        'category' => 'medical',
                        'cert_type' => 'medical_general',
                        'file_path' => $storedPath,
                        'original_filename' => $originalName,
                        'mime_type' => $mime,
                        'size_bytes' => $size,
                        'expiry_date' => $m->cb_datcertif,
                        'is_current' => true,
                        'is_verified' => true,
                    ]);
                }
            }

            $imported++;
        }

        $this->command->info("Imported {$imported} members from Joomla.");
    }

    /** Parse compound cert strings like "N2 PN3" or "N3 E1" into FFESSM codes. */
    private function parseCertification(?string $raw): array
    {
        if (! $raw || $raw === '-' || $raw === 'NULL') {
            return [];
        }

        $codes = [];
        foreach (preg_split('/\s+/', trim($raw)) as $part) {
            $mapped = self::CERT_MAP[$part] ?? null;
            if ($mapped) {
                $codes[] = $mapped;
            }
        }

        return $codes;
    }

    /** Get the VisitesMed directory path. */
    private function medicalDir(): string
    {
        return env('JOOMLA_VISMED_PATH', base_path('../tmp/domains/clubcep.eu/public_html/VisitesMed'));
    }

    /** Find the best medical cert file for a Joomla user ID (prefer recent, then PDF, largest). */
    private function findMedicalCertFile(int $joomlaId): ?string
    {
        $dir = $this->medicalDir();
        if (! is_dir($dir)) {
            return null;
        }

        $cutoff = strtotime('2024-09-01');
        $matches = [];

        foreach (scandir($dir) as $f) {
            if (preg_match('/\s+'.$joomlaId.'\.(pdf|jpe?g|png)$/i', $f, $m)) {
                $path = $dir.'/'.$f;
                $matches[] = [
                    'file' => $f,
                    'ext' => strtolower($m[1]),
                    'size' => filesize($path),
                    'mtime' => filemtime($path),
                ];
            }
        }

        if (! $matches) {
            return null;
        }

        // Prefer files newer than cutoff
        $recent = array_filter($matches, fn ($m) => $m['mtime'] >= $cutoff);
        $pool = $recent ?: $matches;

        // If only old files exist, skip entirely
        if (! $recent) {
            return null;
        }

        // From recent files: prefer PDF, then newest
        $pdfs = array_filter($pool, fn ($m) => $m['ext'] === 'pdf');
        $candidates = $pdfs ?: $pool;

        $pick = null;
        foreach ($candidates as $c) {
            if (! $pick || $c['mtime'] > $pick['mtime']) {
                $pick = $c;
            }
        }

        return $pick['file'];
    }
}
