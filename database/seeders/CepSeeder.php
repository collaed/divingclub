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
use App\Models\DiveSite;
use App\Models\Event;
use App\Models\Federation;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\Season;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CepSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMembers();
        $this->seedArticles('cep_articles.json', 'reusable');
        $this->seedArticles('cep_articles_private.json', 'CEP-specific');
        $this->call(CepEquipmentSeeder::class);
        $this->call(DiveSiteSeeder::class);
        $this->seedEvents();
    }

    /* ------------------------------------------------------------------ */
    /*  Members — from cep_members.json */
    /* ------------------------------------------------------------------ */

    private function seedMembers(): void
    {
        $members = json_decode(file_get_contents(database_path('seeders/data/cep_members.json')), true);
        if (! $members) {
            $this->command->error('No member data found at database/seeders/data/cep_members.json');

            return;
        }

        $roleMap = Role::pluck('id', 'slug')->toArray();
        $statusMap = MemberStatus::pluck('id', 'slug')->toArray();
        $fedMap = Federation::pluck('id', 'acronym')->toArray();

        $certLookup = [];
        CertificationLevel::with('federation')->get()->each(function ($c) use (&$certLookup) {
            $key = ($c->federation?->acronym ?? 'UNKNOWN').':'.$c->code;
            $certLookup[$key] = $c->id;
        });

        $password = Hash::make('cep2026!');
        $created = 0;

        foreach ($members as $m) {
            if (($m['status'] ?? '') === 'inactif') {
                continue;
            }
            if (User::where('primary_email', $m['email'])->exists()) {
                continue;
            }

            $statusSlug = $m['status'] ?? 'actif';
            if (! isset($statusMap[$statusSlug])) {
                $statusSlug = 'actif';
            }

            $user = User::create([
                'username' => $m['username'],
                'primary_email' => $m['email'],
                'password' => $password,
                'role_id' => $roleMap[$m['role']] ?? $roleMap['member'],
                'status_id' => $statusMap[$statusSlug],
                'email_verified_at' => now(),
            ]);

            $parsed = $this->parseAddress($m['address'] ?? '');

            $user->detail()->create([
                'first_name' => $m['first_name'],
                'last_name' => $m['last_name'],
                'birth_name' => $m['birth_name'] ?? null,
                'sex' => $m['sex'] ?? null,
                'date_of_birth' => $m['dob'] ?? null,
                'place_of_birth' => $m['pob'] ?? null,
                'address_line1' => $parsed['street'] ?? $m['address'] ?? null,
                'postal_code' => $parsed['postal'] ?? null,
                'city' => $parsed['city'] ?? null,
                'country' => $parsed['country'] ?? $m['country'] ?? null,
                'nationality' => $m['country'] ?? null,
                'phone_mobile' => $m['phone'] ?? null,
                'phone_office' => $m['phone_office'] ?? null,
                'phone_private' => $m['phone_private'] ?? null,
                'emergency_contact_name' => $m['emergency_name'] ?? null,
                'emergency_contact_phone' => $m['emergency_phone'] ?? null,
                'emergency_contact_relationship' => $m['emergency_relationship'] ?? null,
                'adhesion_year' => $m['adhesion_year'] ?? null,
                'dive_count' => $m['dive_count'] ?? 0,
                'bureau_member' => $m['bureau'] ?? false,
                'active_instructor' => $m['instructor'] ?? false,
                'club_email' => $m['cep_email'] ?? null,
                'instructor_bio' => $m['instructor_bio'] ?? null,
                'instructor_specialties' => $m['instructor_specialties'] ?? null,
                'preferred_language' => $this->guessLanguage($m['country'] ?? ''),
                'iban' => $m['iban'] ?? null,
                'bcd_size' => $m['bcd_size'] ?? null,
                'cotisation_years' => $this->parseCotisationYears($m['cotisation_years'] ?? null),
            ]);

            // Licence
            if (! empty($m['licence_number']) && ! empty($m['federation'])) {
                $fedId = $fedMap[$m['federation']] ?? null;
                if ($fedId) {
                    $user->licences()->create([
                        'federation_id' => $fedId,
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

    /* ------------------------------------------------------------------ */
    /*  Articles — from JSON data files with translations */
    /* ------------------------------------------------------------------ */

    private function seedArticles(string $filename, string $label): void
    {
        $path = database_path("seeders/data/{$filename}");
        if (! file_exists($path)) {
            $this->command->warn("Skipping {$label} articles — {$filename} not found");

            return;
        }

        $articles = json_decode(file_get_contents($path), true);
        if (! $articles) {
            $this->command->warn("Skipping {$label} articles — empty or invalid JSON");

            return;
        }

        $created = 0;

        foreach ($articles as $data) {
            $article = Article::firstOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'article_type' => $data['article_type'],
                    'body' => $data['body'],
                    'is_public' => $data['is_public'],
                    'is_published' => $data['is_published'] ?? true,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'author_id' => User::where('primary_email', 'admin@divingclub.eu')->value('id') ?? 1,
                ]
            );

            if (! $article->wasRecentlyCreated) {
                continue;
            }

            // Seed translations
            foreach ($data['translations'] ?? [] as $t) {
                ArticleTranslation::firstOrCreate(
                    ['article_id' => $article->id, 'locale' => $t['locale']],
                    [
                        'title' => $t['title'],
                        'body' => $t['body'],
                        'auto_translated' => true,
                    ]
                );
            }

            $created++;
        }

        $this->command->info("Seeded {$created} {$label} articles (skipped existing)");
    }

    /* ------------------------------------------------------------------ */
    /*  Events — realistic CEP season */
    /* ------------------------------------------------------------------ */

    private function seedEvents(): void
    {
        $season = Season::firstOrCreate(
            ['year' => 2025],
            ['name' => 'Saison 2025-26', 'start_date' => '2025-09-01', 'end_date' => '2026-06-30', 'is_active' => true]
        );

        $admin = User::where('primary_email', 'eddy.collart@gmail.com')->value('id')
            ?? User::first()?->id ?? 1;

        $diveSites = DiveSite::pluck('id', 'name');
        $events = [];

        // Pool sessions: Tuesdays 19:30-21:00, Sep 2025 – Jun 2026
        $date = Carbon::parse('2025-09-02'); // first Tuesday
        while ($date->dayOfWeek !== Carbon::TUESDAY) {
            $date->addDay();
        }
        while ($date->lte('2026-06-30')) {
            $events[] = [
                'title' => 'Entraînement piscine',
                'event_type' => 'pool', 'color_hex' => '#0077be',
                'event_date' => $date->format('Y-m-d'), 'event_time' => '19:30', 'end_time' => '21:00',
                'location' => 'Piscine Bonnevoie, Luxembourg',
                'max_participants' => 15, 'season_id' => $season->id,
            ];
            $date->addWeek();
        }

        // Theory: Wednesdays 19:00-20:30, Oct-May (every 2 weeks)
        $date = Carbon::parse('2025-10-01');
        while ($date->dayOfWeek !== Carbon::WEDNESDAY) {
            $date->addDay();
        }
        $theoryTopics = ['Tables MN90', 'Loi de Mariotte', 'Loi de Henry', 'Loi de Dalton', 'Barotraumatismes', 'Narcose', 'Hyperoxie', 'Premiers secours', 'Orientation sous-marine', 'Planification de plongée', 'Signes de plongée', 'Biologie marine', 'Matériel de plongée', 'Flottabilité', 'Décompression'];
        $ti = 0;
        while ($date->lte('2026-05-31')) {
            $events[] = [
                'title' => 'Théorie : '.$theoryTopics[$ti % count($theoryTopics)],
                'event_type' => 'theory', 'color_hex' => '#6f42c1',
                'event_date' => $date->format('Y-m-d'), 'event_time' => '19:00', 'end_time' => '20:30',
                'location' => 'Local CEP, Bonnevoie',
                'max_participants' => 25, 'season_id' => $season->id,
            ];
            $date->addWeeks(2);
            $ti++;
        }

        // Dive trips (weekends, Apr-Oct)
        $diveTrips = [
            ['2026-04-18', 'Sortie Vodelée', 'Carrière de Vodelée', 12, '08:00', '17:00'],
            ['2026-05-09', 'Sortie Barges', 'Carrière de Barges', 10, '07:30', '17:00'],
            ['2026-05-23', 'Sortie Lac Haute-Sûre', 'Lac de la Haute-Sûre — Lultzhausen', 12, '08:30', '16:00'],
            ['2026-06-06', 'Sortie Zeeland — Dreischor', 'Grevelingenmeer — Dreischor', 10, '06:00', '19:00'],
            ['2026-06-20', 'Sortie Nemo 33', 'Nemo 33', 15, '09:00', '16:00'],
            ['2026-07-04', 'Sortie Rochefontaine', 'Carrière de Rochefontaine', 10, '08:00', '17:00'],
            ['2026-08-15', 'Sortie Lac Haute-Sûre', 'Lac de la Haute-Sûre — Insenborn', 12, '09:00', '16:00'],
            ['2026-09-12', 'Sortie Floreffe', 'Carrière de Floreffe', 12, '08:00', '17:00'],
            ['2026-09-26', 'Sortie Scharendijke', 'Grevelingenmeer — Scharendijke', 10, '06:00', '19:00'],
            ['2026-10-10', 'Sortie Gravière du Fort', 'Gravière du Fort', 12, '08:30', '16:00'],
        ];
        foreach ($diveTrips as [$d, $title, $site, $max, $start, $end]) {
            $events[] = [
                'title' => $title,
                'event_type' => 'dive', 'color_hex' => '#003366',
                'event_date' => $d, 'event_time' => $start, 'end_time' => $end,
                'location' => $site,
                'max_participants' => $max, 'season_id' => $season->id,
                'dive_site_id' => $diveSites[$site] ?? null,
                'estimated_cost' => rand(15, 45),
            ];
        }

        // Social events
        $socials = [
            ['2025-12-13', 'Repas de Noël du CEP', 'Restaurant Am Tiirmschen, Luxembourg', '19:00', '23:30', 40],
            ['2026-01-17', 'Galette des Rois', 'Local CEP, Bonnevoie', '18:00', '21:00', 30],
            ['2026-03-21', 'Assemblée Générale', 'Salle Bonnevoie, Luxembourg', '14:00', '17:00', 50],
            ['2026-06-27', 'Barbecue de fin de saison', 'Parc Merl, Luxembourg', '12:00', '18:00', 50],
        ];
        foreach ($socials as [$d, $title, $loc, $start, $end, $max]) {
            $events[] = [
                'title' => $title,
                'event_type' => 'social', 'color_hex' => '#ffc107',
                'event_date' => $d, 'event_time' => $start, 'end_time' => $end,
                'location' => $loc,
                'max_participants' => $max, 'season_id' => $season->id,
            ];
        }

        // Multi-day trip
        $events[] = [
            'title' => 'Séjour plongée — Croatie (Rovinj)',
            'event_type' => 'trip', 'color_hex' => '#00838f',
            'event_date' => '2026-07-11', 'end_date' => '2026-07-18',
            'event_time' => '06:00', 'end_time' => '20:00',
            'location' => 'Rovinj, Croatie',
            'max_participants' => 16, 'season_id' => $season->id,
            'estimated_cost' => 850,
            'deposit_1_date' => '2026-03-15', 'deposit_1_amount' => 300,
            'deposit_2_date' => '2026-05-15', 'deposit_2_amount' => 300,
            'deposit_3_date' => '2026-06-30', 'deposit_3_amount' => 250,
        ];

        $created = 0;
        foreach ($events as $e) {
            Event::create(array_merge($e, [
                'created_by' => $admin,
                'status' => 'scheduled',
                'waiting_list_enabled' => true,
            ]));
            $created++;
        }

        $this->command->info("Seeded {$created} CEP events + season '{$season->name}'");
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers */
    /* ------------------------------------------------------------------ */

    private function parseAddress(string $raw): array
    {
        if (! $raw) {
            return [];
        }

        $raw = str_replace([';', ' - '], [',', ','], $raw);
        $parts = array_map('trim', preg_split('/,\s*/', $raw));

        $street = null;
        $postal = null;
        $city = null;
        $country = null;

        // Known country names
        $countryNames = ['Belgique', 'Belgium', 'France', 'Allemagne', 'Germany', 'HONGRIE', 'Hungary', 'Italie', 'Italy', 'Portugal', 'Espagne', 'Pologne', 'Roumanie'];
        $lastPart = end($parts);
        foreach ($countryNames as $c) {
            if (stripos($lastPart, $c) !== false) {
                $country = array_pop($parts);
                break;
            }
        }

        // Rejoin remaining and try to extract postal+city
        $joined = implode(', ', $parts);

        // Pattern: street ... L-XXXX City or street ... XXXX City
        if (preg_match('/^(.+?),?\s+L?-?(\d{4,5})\s+(.+)$/i', $joined, $m)) {
            $street = $m[1];
            $postal = $m[2];
            $city = $m[3];
        } elseif (preg_match('/^(.+?),\s*L?-?(\d{4,5})\s+(.+)$/i', $joined, $m)) {
            $street = $m[1];
            $postal = $m[2];
            $city = $m[3];
        } else {
            // Try comma-separated: street, postal city
            foreach ($parts as $i => $part) {
                if (preg_match('/^L?-?(\d{4,5})\s+(.+)/i', $part, $pm)) {
                    $street = implode(', ', array_slice($parts, 0, $i));
                    $postal = $pm[1];
                    $city = $pm[2];
                    break;
                }
            }
            if (! $street) {
                $street = $joined;
            }
        }

        // Default country for Luxembourg postal codes
        if ($postal && ! $country && strlen($postal) === 4 && $postal >= '1000' && $postal <= '9999') {
            $country = 'Luxembourg';
        }

        return array_filter([
            'street' => $street,
            'postal' => $postal,
            'city' => $city,
            'country' => $country,
        ]);
    }

    private function guessLanguage(string $country): string
    {
        return match (strtolower(trim($country))) {
            'france', 'belgique', 'belgium', 'luxembourg' => 'fr',
            'portugal' => 'pt',
            'allemagne', 'germany', 'austria', 'autriche' => 'de',
            'italie', 'italy' => 'it',
            'espagne', 'spain', 'mexico', 'uruguay' => 'es',
            'pologne', 'poland' => 'pl',
            'hongrie', 'hungary' => 'hu',
            'roumanie', 'romania' => 'ro',
            'pays-bas', 'netherlands' => 'nl',
            default => 'en',
        };
    }

    private function parseCotisationYears(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }
        $years = [];
        foreach (preg_split('/[-,\s]+/', str_replace('->', '-', $raw)) as $y) {
            $y = trim($y);
            if (is_numeric($y) && strlen($y) === 4) {
                $years[] = $y;
            }
        }

        return $years ?: null;
    }
}
