<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Services\ArticleTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class ArticleTranslationUpsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_translate_updates_the_existing_row_for_a_locale_instead_of_inserting_a_duplicate(): void
    {
        config([
            'services.cloudflare.account_id' => 'acc',
            'services.cloudflare.api_token' => 'tok',
        ]);
        Http::fake(['api.cloudflare.com/*' => Http::response(['result' => ['translated_text' => 'translated']], 200)]);

        $article = Article::factory()->create(['title' => 'Bonjour', 'body' => '<p>Texte</p>', 'author_id' => null]);
        $article->translations()->create([
            'locale' => 'en',
            'title' => 'stale title',
            'body' => 'stale body',
            'auto_translated' => true,
            'stale' => true,
        ]);

        $result = app(ArticleTranslationService::class)->translate($article, 'en');

        $this->assertSame(1, ArticleTranslation::where('article_id', $article->id)->where('locale', 'en')->count());
        $this->assertFalse($result->fresh()->stale);
        $this->assertStringContainsString('translated', (string) $result->fresh()->title);
    }

    public function test_translate_failure_path_reuses_the_existing_row_and_does_not_throw(): void
    {
        // No provider credentials configured -> translateText() returns null for
        // both title and body -> failure branch. An existing row must be reused.
        $article = Article::factory()->create(['author_id' => null]);
        $existing = $article->translations()->create([
            'locale' => 'de',
            'title' => 'vorhandener Titel',
            'body' => 'vorhandener Text',
            'auto_translated' => true,
            'stale' => true,
        ]);

        $result = app(ArticleTranslationService::class)->translate($article, 'de');

        $this->assertTrue($existing->is($result));
        $this->assertSame(1, ArticleTranslation::where('article_id', $article->id)->where('locale', 'de')->count());
    }

    public function test_translate_creates_exactly_one_row_when_called_repeatedly(): void
    {
        $article = Article::factory()->create(['author_id' => null]);
        $svc = app(ArticleTranslationService::class);

        $svc->translate($article, 'it');
        $svc->translate($article, 'it');
        $svc->translate($article, 'it');

        $this->assertSame(1, ArticleTranslation::where('article_id', $article->id)->where('locale', 'it')->count());
    }
}
