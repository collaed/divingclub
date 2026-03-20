<?php

/**
 * Private seed data for Club Européen de Plongée (ClubCEP.eu).
 *
 * Seeds real CEP members from JSON, CEP-specific articles (club news,
 * bureau history, etc.), and all reusable technical/training articles
 * with their translations across 11 locales.
 *
 * Run:  php artisan db:seed --class=CepSeeder
 *
 * @author ClubCEP.eu
 */

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CepSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMembers();
        $this->seedArticles('cep_articles.json', 'reusable');
        $this->seedArticles('cep_articles_private.json', 'CEP-specific');
    }

    /* ------------------------------------------------------------------ */
    /*  Members — from cep_members.json                                   */
    /* ------------------------------------------------------------------ */

    private function seedMembers(): void
    {
        $members = json_decode(file_get_contents(database_path('seeders/data/cep_members.json')), true);
        if (!$members) {
            $this->command->error('No member data found at database/seeders/data/cep_members.json');
            return;
        }

        $roleMap   = Role::pluck('id', 'slug')->toArray();
        $statusMap = MemberStatus::pluck('id', 'slug')->toArray();
        $fedMap    = Federation::pluck('id', 'acronym')->toArray();

        $certLookup = [];
        CertificationLevel::with('federation')->get()->each(function ($c) use (&$certLookup) {
            $key = ($c->federation?->acronym ?? 'UNKNOWN') . ':' . $c->code;
            $certLookup[$key] = $c->id;
        });

        $password = Hash::make('cep2026!');
        $created  = 0;

        foreach ($members as $m) {
            if (($m['status'] ?? '') === 'inactif') continue;
            if (User::where('primary_email', $m['email'])->exists()) continue;

            $statusSlug = $m['status'] ?? 'actif';
            if (!isset($statusMap[$statusSlug])) $statusSlug = 'actif';

            $user = User::create([
                'username'          => $m['username'],
                'primary_email'     => $m['email'],
                'password'          => $password,
                'role_id'           => $roleMap[$m['role']] ?? $roleMap['member'],
                'status_id'         => $statusMap[$statusSlug],
                'email_verified_at' => now(),
            ]);

            $user->detail()->create([
                'first_name'              => $m['first_name'],
                'last_name'               => $m['last_name'],
                'birth_name'              => $m['birth_name'] ?? null,
                'sex'                     => $m['sex'] ?? null,
                'date_of_birth'           => $m['dob'] ?? null,
                'place_of_birth'          => $m['pob'] ?? null,
                'address_line1'           => $m['address'] ?? null,
                'country'                 => $m['country'] ?? null,
                'phone_mobile'            => $m['phone'] ?? null,
                'phone_office'            => $m['phone_office'] ?? null,
                'emergency_contact_name'  => $m['emergency_name'] ?? null,
                'emergency_contact_phone' => $m['emergency_phone'] ?? null,
                'adhesion_year'           => $m['adhesion_year'] ?? null,
                'dive_count'              => $m['dive_count'] ?? 0,
                'bureau_member'           => $m['bureau'] ?? false,
                'active_instructor'       => $m['instructor'] ?? false,
                'cep_email'               => $m['cep_email'] ?? null,
                'instructor_bio'          => $m['instructor_bio'] ?? null,
                'instructor_specialties'  => $m['instructor_specialties'] ?? null,
                'nationality'             => $m['country'] ?? null,
                'cotisation_years'        => $this->parseCotisationYears($m['cotisation_years'] ?? null),
            ]);

            // Licence
            if (!empty($m['licence_number']) && !empty($m['federation'])) {
                $fedId = $fedMap[$m['federation']] ?? null;
                if ($fedId) {
                    $user->licences()->create([
                        'federation_id'  => $fedId,
                        'licence_number' => $m['licence_number'],
                        'federation_key' => $m['federation_key'] ?? null,
                    ]);
                }
            }

            // Certifications
            foreach ($m['certs'] ?? [] as $cert) {
                $certId = $certLookup[$cert] ?? null;
                if ($certId) {
                    $user->certificationLevels()->attach($certId, [
                        'is_primary'  => $cert === ($m['certs'][0] ?? ''),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            $created++;
        }

        $this->command->info("Created {$created} CEP members (password: cep2026!)");
    }

    /* ------------------------------------------------------------------ */
    /*  Articles — from JSON data files with translations                 */
    /* ------------------------------------------------------------------ */

    private function seedArticles(string $filename, string $label): void
    {
        $path = database_path("seeders/data/{$filename}");
        if (!file_exists($path)) {
            $this->command->warn("Skipping {$label} articles — {$filename} not found");
            return;
        }

        $articles = json_decode(file_get_contents($path), true);
        if (!$articles) {
            $this->command->warn("Skipping {$label} articles — empty or invalid JSON");
            return;
        }

        $created = 0;

        foreach ($articles as $data) {
            $article = Article::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'title'        => $data['title'],
                    'article_type' => $data['article_type'],
                    'body'         => $data['body'],
                    'is_public'    => $data['is_public'],
                    'is_published' => $data['is_published'] ?? true,
                    'sort_order'   => $data['sort_order'] ?? 0,
                    'author_id'    => 1,
                ]
            );

            if (!$article->wasRecentlyCreated) continue;

            // Seed translations
            foreach ($data['translations'] ?? [] as $t) {
                ArticleTranslation::firstOrCreate(
                    ['article_id' => $article->id, 'locale' => $t['locale']],
                    [
                        'title'           => $t['title'],
                        'body'            => $t['body'],
                        'auto_translated' => true,
                    ]
                );
            }

            $created++;
        }

        $this->command->info("Seeded {$created} {$label} articles (skipped existing)");
    }

    /* ------------------------------------------------------------------ */

    private function parseCotisationYears(?string $raw): ?array
    {
        if (!$raw) return null;
        $years = [];
        foreach (preg_split('/[-,\s]+/', str_replace('->', '-', $raw)) as $y) {
            $y = trim($y);
            if (is_numeric($y) && strlen($y) === 4) $years[] = $y;
        }
        return $years ?: null;
    }
}
