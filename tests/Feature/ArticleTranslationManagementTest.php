<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\User;
use App\Services\ArticleTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class ArticleTranslationManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $bureau;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bureau = $this->createBureauUser();

        $this->article = Article::create([
            'title' => 'La date a changé',
            'slug' => 'la-date-a-change-'.uniqid(),
            'body' => '<p>La conférence aura lieu le 30/09.</p>',
            'article_type' => 'news',
            'is_published' => true,
            'author_id' => $this->bureau->id,
        ]);
    }

    public function test_bureau_can_view_the_translations_status_grid(): void
    {
        $this->actingAs($this->bureau)
            ->get(route('admin.articles.translations', $this->article))
            ->assertOk()
            ->assertSee('Not yet translated');
    }

    public function test_editing_the_french_locale_redirects_to_the_normal_article_form(): void
    {
        $this->actingAs($this->bureau)
            ->get(route('admin.articles.translations.edit', [$this->article, 'fr']))
            ->assertRedirect(route('admin.articles.edit', $this->article));
    }

    public function test_an_unknown_locale_code_404s_instead_of_reaching_the_controller(): void
    {
        $this->actingAs($this->bureau)
            ->get('/admin/articles/'.$this->article->id.'/translations/xx')
            ->assertNotFound();
    }

    public function test_regular_member_cannot_access_translation_management(): void
    {
        $member = $this->createMemberUser();

        $this->actingAs($member)
            ->get(route('admin.articles.translations', $this->article))
            ->assertForbidden();
    }

    public function test_bureau_can_save_a_manual_translation_and_it_is_sanitized(): void
    {
        $this->actingAs($this->bureau)
            ->put(route('admin.articles.translations.update', [$this->article, 'de']), [
                'title' => 'Das Datum hat sich geändert',
                'body' => '<p>Die Konferenz findet am 14/10 statt.</p><script>alert(1)</script>',
            ])
            ->assertRedirect(route('admin.articles.translations.edit', [$this->article, 'de']));

        // firstOrFail(), not first(): its return type is the model itself
        // (it throws instead of returning null), so every dereference below
        // is on a value static analysis can see is never null — a plain
        // first() + assertNotNull() leaves that only true at runtime.
        $translation = $this->article->translations()->where('locale', 'de')->firstOrFail();

        $this->assertSame('Das Datum hat sich geändert', $translation->title);
        $this->assertStringContainsString('14/10', $translation->body);
        $this->assertStringNotContainsString('<script>', $translation->body);
        $this->assertFalse($translation->auto_translated);
        $this->assertFalse($translation->stale);
    }

    public function test_a_manually_curated_translation_is_never_overwritten_by_a_non_forced_pass(): void
    {
        ArticleTranslation::create([
            'article_id' => $this->article->id,
            'locale' => 'de',
            'title' => 'Manuell korrigiert',
            'body' => '<p>Am 14/10.</p>',
            'auto_translated' => false,
            'stale' => false,
            'source_hash' => ArticleTranslationService::sourceHash($this->article),
        ]);

        Http::fake();

        app(ArticleTranslationService::class)->translateAll($this->article, ['de'], 'fr');

        Http::assertNothingSent();
        $translation = $this->article->translations()->where('locale', 'de')->firstOrFail();
        $this->assertSame('Manuell korrigiert', $translation->title);
        $this->assertFalse($translation->auto_translated);
    }

    public function test_regenerate_with_force_overwrites_a_manually_curated_translation(): void
    {
        config([
            'services.cloudflare.account_id' => 'test-account',
            'services.cloudflare.api_token' => 'test-token',
        ]);

        ArticleTranslation::create([
            'article_id' => $this->article->id,
            'locale' => 'de',
            'title' => 'Alte manuelle Fassung',
            'body' => '<p>Alt.</p>',
            'auto_translated' => false,
            'stale' => false,
            'source_hash' => 'stale-hash',
        ]);

        Http::fake([
            '*ai/run/@cf/meta/m2m100-1.2b' => Http::response(['result' => ['translated_text' => 'Regenerierter Titel']]),
        ]);

        $this->actingAs($this->bureau)
            ->post(route('admin.articles.translations.regenerate', [$this->article, 'de']))
            ->assertRedirect(route('admin.articles.translations.edit', [$this->article, 'de']));

        $translation = $this->article->translations()->where('locale', 'de')->firstOrFail();
        $this->assertSame('Regenerierter Titel', $translation->title);
        $this->assertTrue($translation->auto_translated);
    }
}
