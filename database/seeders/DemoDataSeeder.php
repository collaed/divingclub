<?php

namespace Database\Seeders;

use App\Models\CertificationLevel;
use App\Models\Document;
use App\Models\Federation;
use App\Models\MemberDetail;
use App\Models\MemberLicence;
use App\Models\Role;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('demo1234');

        // Ensure roles exist
        $roles = [];
        foreach (['bureau_master', 'bureau_member', 'instructor', 'assistant', 'member', 'pending'] as $slug) {
            $roles[$slug] = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst(str_replace('_', ' ', $slug))]);
        }

        $flassa = Federation::where('acronym', 'FLASSA')->first();
        $lifras = Federation::where('acronym', 'LIFRAS')->first();
        $padi = Federation::where('acronym', 'PADI')->first();
        $ffessm = Federation::where('acronym', 'FFESSM')->first();

        $members = [
            // Bureau
            ['first' => 'Marc', 'last' => 'Dupont', 'role' => 'bureau_master', 'fed' => $flassa, 'cert' => 'M2', 'dob' => '1975-03-15', 'phone' => '+352 621 100 001', 'address' => '12 Rue de la Gare, 1616 Luxembourg', 'instructor_bio' => 'Moniteur 2 étoiles depuis 2005. Passionné de plongée technique et de formation.', 'instructor_specialties' => 'Deep diving, Nitrox, Formation P1-P3', 'instructor_motivation' => 'Transmettre la passion de la plongée et former la prochaine génération de plongeurs.'],
            ['first' => 'Sophie', 'last' => 'Muller', 'role' => 'bureau_master', 'fed' => $flassa, 'cert' => 'M1', 'dob' => '1980-07-22', 'phone' => '+352 621 100 002', 'address' => '45 Avenue de la Liberté, 1931 Luxembourg', 'instructor_bio' => 'Monitrice depuis 2010. Spécialiste de la plongée en carrière.', 'instructor_specialties' => 'Carrière diving, Orientation, Rescue', 'instructor_motivation' => 'Aider les débutants à découvrir le monde sous-marin en toute sécurité.'],
            ['first' => 'Jean-Pierre', 'last' => 'Schmit', 'role' => 'bureau_master', 'fed' => $lifras, 'cert' => 'P4', 'dob' => '1968-11-03', 'phone' => '+352 621 100 003', 'address' => '8 Rue du Fossé, 1536 Luxembourg'],

            // Instructors
            ['first' => 'Nathalie', 'last' => 'Weber', 'role' => 'instructor', 'fed' => $flassa, 'cert' => 'M1', 'dob' => '1982-05-10', 'phone' => '+352 691 200 001', 'address' => '23 Rue de Strasbourg, 2561 Luxembourg', 'instructor_bio' => 'Monitrice 1 étoile, plongeuse depuis 15 ans.', 'instructor_specialties' => 'Formation P1, Baptêmes, Apnée', 'instructor_motivation' => 'Le sourire des élèves après leur première plongée.'],
            ['first' => 'Thomas', 'last' => 'Kieffer', 'role' => 'instructor', 'fed' => $padi, 'cert' => 'OWSI', 'dob' => '1985-09-28', 'phone' => '+352 691 200 002', 'address' => '67 Route d\'Esch, 1470 Luxembourg', 'instructor_bio' => 'PADI Open Water Scuba Instructor. Dive travel enthusiast.', 'instructor_specialties' => 'PADI courses, Wreck diving, Underwater photography', 'instructor_motivation' => 'Sharing the beauty of the underwater world with everyone.'],

            // Assistants
            ['first' => 'Léa', 'last' => 'Hoffmann', 'role' => 'assistant', 'fed' => $flassa, 'cert' => 'P3', 'dob' => '1990-02-14', 'phone' => '+352 691 300 001', 'address' => '5 Rue de Bonnevoie, 1260 Luxembourg'],

            // Experienced members
            ['first' => 'Pierre', 'last' => 'Reuter', 'role' => 'member', 'fed' => $flassa, 'cert' => 'P3', 'dob' => '1978-12-01', 'phone' => '+352 621 400 001', 'address' => '34 Rue de Hollerich, 1741 Luxembourg'],
            ['first' => 'Isabelle', 'last' => 'Thill', 'role' => 'member', 'fed' => $lifras, 'cert' => 'P2', 'dob' => '1988-04-18', 'phone' => '+352 621 400 002', 'address' => '19 Rue de Beggen, 1221 Luxembourg'],
            ['first' => 'François', 'last' => 'Majerus', 'role' => 'member', 'fed' => $flassa, 'cert' => 'P2', 'dob' => '1992-08-05', 'phone' => '+352 691 400 003', 'address' => '78 Rue de Merl, 2146 Luxembourg'],
            ['first' => 'Caroline', 'last' => 'Becker', 'role' => 'member', 'fed' => $ffessm, 'cert' => 'N2', 'dob' => '1995-01-30', 'phone' => '+33 6 12 34 56 78', 'address' => '15 Rue des Roses, 57100 Thionville, France'],

            // Beginners
            ['first' => 'Lucas', 'last' => 'Reding', 'role' => 'member', 'fed' => $flassa, 'cert' => 'P1', 'dob' => '1998-06-12', 'phone' => '+352 691 500 001', 'address' => '42 Rue de Gasperich, 1617 Luxembourg'],
            ['first' => 'Emma', 'last' => 'Schiltz', 'role' => 'member', 'fed' => $flassa, 'cert' => 'P1', 'dob' => '2000-03-25', 'phone' => '+352 691 500 002', 'address' => '11 Rue de Clausen, 1342 Luxembourg'],
            ['first' => 'David', 'last' => 'Fernandes', 'role' => 'member', 'fed' => $padi, 'cert' => 'OWD', 'dob' => '1993-10-08', 'phone' => '+352 621 500 003', 'address' => '56 Rue de Neudorf, 2221 Luxembourg'],

            // Pending / new
            ['first' => 'Anna', 'last' => 'Kovacs', 'role' => 'pending', 'fed' => null, 'cert' => null, 'dob' => '1997-07-19', 'phone' => '+352 691 600 001', 'address' => '3 Rue de Limpertsberg, 1932 Luxembourg'],
            ['first' => 'Miguel', 'last' => 'Santos', 'role' => 'pending', 'fed' => null, 'cert' => null, 'dob' => '2001-11-22', 'phone' => '+352 621 600 002', 'address' => '88 Route d\'Arlon, 1150 Luxembourg'],
        ];

        $certTypes = ['gp', 'ent', 'sport'];

        foreach ($members as $i => $m) {
            $email = strtolower($m['first'] . '.' . str_replace(' ', '', $m['last'])) . '@example.com';

            $user = User::firstOrCreate(
                ['primary_email' => $email],
                [
                    'primary_email' => $email,
                    'password' => $password,
                    'role_id' => $roles[$m['role']]->id,
                    'email_verified_at' => $m['role'] !== 'pending' ? now() : null,
                ]
            );

            UserEmail::firstOrCreate(
                ['user_id' => $user->id, 'email' => $email],
                ['is_primary' => true, 'is_verified' => $m['role'] !== 'pending']
            );

            // Member details
            $detailData = [
                'first_name' => $m['first'],
                'last_name' => $m['last'],
                'date_of_birth' => $m['dob'],
                'phone_mobile' => $m['phone'],
                'address_line1' => explode(',', $m['address'])[0] ?? $m['address'],
                'postal_code' => trim(preg_match('/(\d{4,5})/', $m['address'], $pc) ? $pc[1] : ''),
                'city' => trim(preg_replace('/.*\d{4,5}\s*/', '', $m['address'])),
                'country' => str_contains($m['address'], 'France') ? 'FR' : 'LU',
                'nationality' => in_array($m['role'], ['pending']) ? null : 'LU',
                'preferred_language' => str_contains($m['address'] ?? '', 'France') ? 'fr' : 'en',
            ];
            if (isset($m['instructor_bio'])) {
                $detailData['instructor_bio'] = $m['instructor_bio'];
                $detailData['instructor_specialties'] = $m['instructor_specialties'];
                $detailData['instructor_motivation'] = $m['instructor_motivation'];
            }
            MemberDetail::updateOrCreate(['user_id' => $user->id], $detailData);

            // Federation licence
            if ($m['fed']) {
                MemberLicence::firstOrCreate(
                    ['user_id' => $user->id, 'federation_id' => $m['fed']->id],
                    ['licence_number' => strtoupper($m['fed']->acronym) . '-' . str_pad($user->id, 5, '0', STR_PAD_LEFT)]
                );
            }

            // Certification level
            if ($m['cert'] && $m['fed']) {
                $certLevel = CertificationLevel::where('federation_id', $m['fed']->id)->where('code', $m['cert'])->first();
                if ($certLevel) {
                    $user->certificationLevels()->syncWithoutDetaching([
                        $certLevel->id => ['obtained_date' => now()->subYears(rand(1, 5))->format('Y-m-d'), 'is_primary' => true, 'display_priority' => 1]
                    ]);
                }
            }

            // Generate fake medical certificate for non-pending members
            if ($m['role'] !== 'pending') {
                $certType = $certTypes[array_rand($certTypes)];
                $issueDate = now()->subMonths(rand(1, 8));

                // Create a fake PDF-like file
                $fakeCert = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>\nendobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%EOF\n% Medical Certificate for " . $m['first'] . ' ' . $m['last'] . "\n% Type: " . strtoupper($certType) . "\n% Date: " . $issueDate->format('Y-m-d');

                $dir = 'documents/' . $user->id;
                Storage::makeDirectory($dir);
                $filename = 'medical_cert_' . $certType . '_' . $issueDate->format('Y') . '.pdf';
                Storage::put($dir . '/' . $filename, $fakeCert);

                Document::firstOrCreate(
                    ['user_id' => $user->id, 'category' => 'medical_certificate', 'is_current' => true],
                    [
                        'file_path' => $dir . '/' . $filename,
                        'original_filename' => 'Certificat_Medical_' . strtoupper($certType) . '_' . $m['last'] . '.pdf',
                        'mime_type' => 'application/pdf',
                        'size_bytes' => strlen($fakeCert),
                        'date_established' => $issueDate->format('Y-m-d'),
                        'cert_type' => $certType,
                        'is_verified' => rand(0, 1) ? true : false,
                        'is_current' => true,
                    ]
                );
            }

            echo ($i + 1) . '. ' . $m['first'] . ' ' . $m['last'] . ' (' . $m['role'] . ')' . ($m['cert'] ? ' — ' . $m['cert'] : '') . PHP_EOL;
        }
    }
}
