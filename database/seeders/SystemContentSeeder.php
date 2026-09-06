<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Helpers\SystemContent;
use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Ensures the stable-slug "system" articles exist so the bureau always has a
 * row to edit from the article admin, and application code can embed them:
 *  - sys-dues-footer  : editable footer shown at the bottom of /dues
 *  - sys-home-landing : editable public landing (home3) article
 *
 * Idempotent: only creates missing shells; never overwrites bureau edits.
 */
class SystemContentSeeder extends Seeder
{
    public function run(): void
    {
        SystemContent::ensure(
            SystemContent::DUES_FOOTER,
            'Dues — footer note',
            '<p>Les cotisations sont dues pour la saison en cours. Pour toute question, contactez le bureau.</p>',
            ['article_type' => 'regulation', 'is_public' => true],
        );

        SystemContent::ensure(
            SystemContent::HOME_LANDING,
            'Bienvenue au Club Européen de Plongée',
            $this->defaultLandingBody(),
            ['article_type' => 'news', 'is_public' => true],
        );

        $this->command?->info('System content articles ensured (dues footer, home landing).');
    }

    private function defaultLandingBody(): string
    {
        return <<<'HTML'
<p class="lead">Plongée sous-marine au cœur de l'Europe — formation, sorties et convivialité depuis 1974.</p>
<p>Le Club Européen de Plongée accueille les plongeurs de tous niveaux, du baptême à l'encadrement.
Rejoignez-nous pour découvrir le monde sous-marin en toute sécurité.</p>
HTML;
    }
}
