<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessTranslations;
use App\Models\Article;
use App\Services\ArticleTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationGapFillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'languages' => ['fr' => [], 'en' => [], 'de' => [], 'nl' => []],
            'services.cloudflare.account_id' => 'acc',
            'services.cloudflare.api_token' => 'tok',
        ]);
    }

    public function test_translate_all_keeps_going_after_a_failing_locale(): void
    {
        $svc = $this->partialMock(ArticleTranslationService::class, function ($m): void {
            $m->shouldReceive('translate')->withArgs(fn ($a, $l) => $l === 'de')->andThrow(new \RuntimeException('provider down'));
            $m->shouldReceive('translate')->withArgs(fn ($a, $l) => $l !== 'de')->andReturnUsing(
                fn (Article $a, string $l) => $a->translations()->create(['locale' => $l, 'title' => 'x', 'body' => 'y', 'auto_translated' => true])
            );
        });

        $article = Article::factory()->create(['author_id' => null]);
        $svc->translateAll($article, ['fr', 'en', 'de', 'nl']);

        $this->assertEqualsCanonicalizing(['en', 'nl'], $article->translations()->pluck('locale')->all());
    }

    public function test_process_translations_backfills_missing_locales_on_a_partial_article(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['result' => ['translated_text' => 'translated']], 200)]);

        $article = Article::factory()->create(['title' => 'Bonjour', 'body' => '<p>Texte</p>', 'author_id' => null, 'is_published' => true]);
        $article->translations()->create(['locale' => 'en', 'title' => 'Hi', 'body' => 'Text', 'auto_translated' => true, 'stale' => false]);

        (new ProcessTranslations)->handle();

        $this->assertEqualsCanonicalizing(['en', 'de', 'nl'], $article->translations()->pluck('locale')->sort()->values()->all());
    }
}
