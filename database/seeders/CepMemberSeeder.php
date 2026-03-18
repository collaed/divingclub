<?php

namespace Database\Seeders;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CepMemberSeeder extends Seeder
{
    /**
     * Private seed data for Club Européen de Plongée members.
     * Run: php artisan db:seed --class=CepMemberSeeder
     */
    public function run(): void
    {
        $members = json_decode(file_get_contents(database_path('seeders/data/cep_members.json')), true);
        if (!$members) {
            $this->command->error('No member data found at database/seeders/data/cep_members.json');
            return;
        }

        $roleMap = Role::pluck('id', 'slug')->toArray();
        $statusMap = MemberStatus::pluck('id', 'slug')->toArray();
        $fedMap = Federation::pluck('id', 'acronym')->toArray();

        // Build cert lookup: "FFESSM:N3" => cert_level_id
        $certLookup = [];
        CertificationLevel::with('federation')->get()->each(function ($c) use (&$certLookup) {
            $key = ($c->federation?->acronym ?? 'UNKNOWN') . ':' . $c->code;
            $certLookup[$key] = $c->id;
        });

        $password = Hash::make('cep2026!');
        $created = 0;

        foreach ($members as $m) {
            // Skip inactive/blocked members
            if (($m['status'] ?? '') === 'inactif') continue;

            if (User::where('primary_email', $m['email'])->exists()) {
                continue;
            }

            $statusSlug = $m['status'] ?? 'actif';
            if (!isset($statusMap[$statusSlug])) $statusSlug = 'actif';

            $user = User::create([
                'username' => $m['username'],
                'primary_email' => $m['email'],
                'password' => $password,
                'role_id' => $roleMap[$m['role']] ?? $roleMap['member'],
                'status_id' => $statusMap[$statusSlug],
                'email_verified_at' => now(),
            ]);

            $user->detail()->create([
                'first_name' => $m['first_name'],
                'last_name' => $m['last_name'],
                'birth_name' => $m['birth_name'] ?? null,
                'sex' => $m['sex'] ?? null,
                'date_of_birth' => $m['dob'] ?? null,
                'place_of_birth' => $m['pob'] ?? null,
                'address_line1' => $m['address'] ?? null,
                'country' => $m['country'] ?? null,
                'phone_mobile' => $m['phone'] ?? null,
                'phone_office' => $m['phone_office'] ?? null,
                'emergency_contact_name' => $m['emergency_name'] ?? null,
                'emergency_contact_phone' => $m['emergency_phone'] ?? null,
                'adhesion_year' => $m['adhesion_year'] ?? null,
                'dive_count' => $m['dive_count'] ?? 0,
                'bureau_member' => $m['bureau'] ?? false,
                'active_instructor' => $m['instructor'] ?? false,
                'cep_email' => $m['cep_email'] ?? null,
                'instructor_bio' => $m['instructor_bio'] ?? null,
                'instructor_specialties' => $m['instructor_specialties'] ?? null,
                'nationality' => $m['country'] ?? null,
                'cotisation_years' => $m['cotisation_years'] ?? null,
            ]);

            // Licence
            if (!empty($m['licence_number']) && !empty($m['federation'])) {
                $fedId = $fedMap[$m['federation']] ?? null;
                if ($fedId) {
                    $user->licences()->create([
                        'federation_id' => $fedId,
                        'licence_number' => $m['licence_number'],
                    ]);
                }
            }

            // Certifications
            foreach ($m['certs'] ?? [] as $cert) {
                $certId = $certLookup[$cert] ?? null;
                if ($certId) {
                    $user->certificationLevels()->attach($certId, [
                        'is_primary' => $cert === ($m['certs'][0] ?? ''),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $created++;
        }

        $this->command->info("Created {$created} CEP members (password: cep2026!)");
    }
}
