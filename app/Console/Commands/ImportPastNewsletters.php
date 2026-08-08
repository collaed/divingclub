<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Newsletter;
use Illuminate\Console\Command;

class ImportPastNewsletters extends Command
{
    protected $signature = 'newsletters:import-past';

    protected $description = 'Import April/May/June 2026 newsletters from static HTML files';

    /** @var array<int, array{title: string, slug: string, type: string, teaser: string}> */
    private array $aprilArticles = [
        '201' => ['title' => 'Un mot du Président', 'slug' => 'mot-du-president-avril-2026', 'type' => 'news', 'teaser' => 'Le Printemps est de retour — TODI, Gravière, Juan-les-Pins… Bonnes plongées !'],
        '202' => ['title' => 'Le Nitrox, pour vous ?', 'slug' => 'nitrox-pour-vous-avril-2026', 'type' => 'training', 'teaser' => 'Avantages, compromis, Simple vs Avancé. Tout savoir avant de se lancer.'],
        '203' => ['title' => 'Prochaines Immersions — Calendrier', 'slug' => 'calendrier-sorties-avril-2026', 'type' => 'news', 'teaser' => 'Gravière, Juan-les-Pins, Spillfest et bien plus...'],
        '204' => ['title' => 'Soupe de lettres', 'slug' => 'soupe-de-lettres-avril-2026', 'type' => 'news', 'teaser' => '21 mots cachés ! Octopus, Scaphandre, Cousteau… Trouvez-les tous !'],
        '205' => ['title' => "Règles d'or du binôme", 'slug' => 'regles-or-binome-avril-2026', 'type' => 'safety', 'teaser' => '6 règles essentielles pour plonger en sécurité.'],
    ];

    /** @var array<int, array{title: string, slug: string, type: string, teaser: string}> */
    private array $mayArticles = [
        '301' => ['title' => 'Les Maldives, entre rêve et réalité', 'slug' => 'maldives-reve-realite-mai-2026', 'type' => 'trip_report', 'teaser' => "Par Pascale Lucietto — 5 ans d'instructeur PADI aux Maldives."],
        '302' => ['title' => 'Baptêmes — Relais pour la Vie', 'slug' => 'baptemes-relais-vie-mai-2026', 'type' => 'news', 'teaser' => 'Des jeunes découvrent la plongée pour une bonne cause.'],
        '303' => ['title' => 'Prochaines Immersions', 'slug' => 'prochaines-immersions-mai-2026', 'type' => 'news', 'teaser' => 'Juan-les-Pins, Spillfest, BBQ, Kayak…'],
        '304' => ['title' => "Mieux voir sous l'eau", 'slug' => 'mieux-voir-sous-eau-mai-2026', 'type' => 'gear', 'teaser' => 'Solutions optiques pour masques — myopie, progressifs, lentilles.'],
        '305' => ['title' => 'La flottabilité parfaite', 'slug' => 'flottabilite-parfaite-mai-2026', 'type' => 'training', 'teaser' => 'Trouver votre lestage idéal — les 2 tests faciles.'],
    ];

    /** @var array<int, array{title: string, slug: string, type: string, teaser: string}> */
    private array $juneArticles = [
        '401' => ['title' => 'Grand Match : Gravière du Fort', 'slug' => 'grand-match-graviere-du-fort-juin-2026', 'type' => 'trip_report', 'teaser' => 'La fraîcheur du fond vs la chaleur humaine — et la chaleur gagne.'],
        '402' => ['title' => 'La Mise à Jour (Ennuyeuse Mais Nécessaire)', 'slug' => 'mise-a-jour-club-juin-2026', 'type' => 'news', 'teaser' => 'Finances, van FLASSA, fin des km, ASBL…'],
        '403' => ['title' => 'Deux générations, une même plongée', 'slug' => 'deux-generations-juan-les-pins-juin-2026', 'type' => 'trip_report', 'teaser' => 'Marta et son père partagent leur première plongée ensemble.'],
        '404' => ['title' => "L'eau d'ici — par EauMer", 'slug' => 'leau-dici-par-eaumer-juin-2026', 'type' => 'trip_report', 'teaser' => 'Une odyssée homérique dans les profondeurs de la Glacière.'],
        '405' => ['title' => 'À venir — Ne manquez pas !', 'slug' => 'a-venir-ne-manquez-pas-juin-2026', 'type' => 'news', 'teaser' => 'Staubotz, Kayak, Journée Portes Ouvertes, Cabo Verde.'],
    ];

    public function handle(): int
    {
        $authorId = 1; // Admin user on staging

        // --- APRIL ---
        $this->info('Importing April 2026...');
        $aprilSlots = $this->importArticles($this->aprilArticles, $authorId);
        $aprilHtml = file_get_contents(storage_path('app/newsletter-avril-2026.html'));
        Newsletter::updateOrCreate(
            ['month' => '2026-04'],
            [
                'title' => '🤿 Bulles et Aventures — Avril 2026',
                'background_image' => 'gradient-graviere',
                'slots' => $aprilSlots,
                'published_html' => $aprilHtml,
                'status' => 'sent',
                'sent_at' => '2026-04-15 10:00:00',
                'created_by' => $authorId,
            ]
        );

        // --- MAY ---
        $this->info('Importing May 2026...');
        $maySlots = $this->importArticles($this->mayArticles, $authorId);
        $mayHtml = file_get_contents(storage_path('app/newsletter-mai-2026.html'));
        Newsletter::updateOrCreate(
            ['month' => '2026-05'],
            [
                'title' => '🤿 Bulles et Aventures — Mai 2026',
                'background_image' => 'gradient-juanlespins',
                'slots' => $maySlots,
                'published_html' => $mayHtml,
                'status' => 'sent',
                'sent_at' => '2026-05-19 10:00:00',
                'created_by' => $authorId,
            ]
        );

        // --- JUNE ---
        $this->info('Importing June 2026...');
        $juneSlots = $this->importArticles($this->juneArticles, $authorId);
        $juneHtml = file_get_contents(storage_path('app/newsletter-juin-2026.html'));
        Newsletter::updateOrCreate(
            ['month' => '2026-06'],
            [
                'title' => '🤿 Bulles et Aventures — Juin 2026',
                'background_image' => 'newsletters/june-2026-banner.jpg',
                'slots' => $juneSlots,
                'published_html' => $juneHtml,
                'status' => 'sent',
                'sent_at' => '2026-06-15 18:00:00',
                'created_by' => $authorId,
            ]
        );

        $this->info('Done! 3 newsletters imported.');

        return 0;
    }

    /** @return array<int, array{position: int, article_id: int, article_type: string, teaser: string}> */
    private function importArticles(array $articles, int $authorId): array
    {
        $slots = [];
        $pos = 1;
        foreach ($articles as $fileId => $meta) {
            $body = $this->extractBody($fileId);

            $article = Article::updateOrCreate(
                ['slug' => $meta['slug']],
                [
                    'title' => $meta['title'],
                    'article_type' => $meta['type'],
                    'body' => $body,
                    'is_published' => true,
                    'is_public' => false,
                    'author_id' => $authorId,
                ]
            );

            $slots[] = [
                'position' => $pos,
                'article_id' => $article->id,
                'article_type' => $meta['type'],
                'teaser' => $meta['teaser'],
                'slug' => $meta['slug'],
            ];
            $pos++;
        }

        return $slots;
    }

    private function extractBody(string|int $fileId): string
    {
        $path = public_path("articles/{$fileId}.html");
        if (! file_exists($path)) {
            $this->warn("  File not found: {$path}, using placeholder");

            return '<p><em>Content pending import.</em></p>';
        }

        $html = file_get_contents($path);

        // Extract content between card-body tags
        if (preg_match('/<div class="card-body">(.*?)<\/div><\/div>/s', $html, $m)) {
            return trim($m[1]);
        }

        // Fallback: extract body content
        if (preg_match('/<body[^>]*>(.*?)<\/body>/s', $html, $m)) {
            return trim($m[1]);
        }

        return '<p><em>Could not extract content.</em></p>';
    }
}
