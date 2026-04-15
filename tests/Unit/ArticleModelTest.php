<?php

namespace Tests\Unit;

use App\Models\Article;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * @group p1
 */
class ArticleModelTest extends TestCase
{
    public function test_type_meta_returns_correct_data_for_known_type(): void
    {
        $article = new Article(['article_type' => 'safety']);
        $meta = $article->typeMeta();

        $this->assertSame('🛟', $meta['icon']);
        $this->assertSame('#dc3545', $meta['color']);
        $this->assertSame('Safety', $meta['label']);
    }

    public function test_type_meta_falls_back_to_news_for_unknown(): void
    {
        $article = new Article(['article_type' => 'nonexistent']);
        $meta = $article->typeMeta();

        $this->assertSame('📰', $meta['icon']);
        $this->assertSame('News', $meta['label']);
    }

    public function test_is_expired_when_past(): void
    {
        $article = new Article;
        $article->expires_at = Carbon::yesterday();

        $this->assertTrue($article->isExpired());
    }

    public function test_is_not_expired_when_future(): void
    {
        $article = new Article;
        $article->expires_at = Carbon::tomorrow();

        $this->assertFalse($article->isExpired());
    }

    public function test_is_not_expired_when_null(): void
    {
        $article = new Article;
        $article->expires_at = null;

        $this->assertFalse($article->isExpired());
    }

    public function test_rendered_body_embeds_youtube_url(): void
    {
        $article = new Article;
        $article->body = 'Check this: https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $article->renderedBody());
    }

    public function test_rendered_body_embeds_youtu_be_short_url(): void
    {
        $article = new Article;
        $article->body = 'Watch: https://youtu.be/dQw4w9WgXcQ';

        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $article->renderedBody());
    }

    public function test_rendered_body_embeds_vimeo_url(): void
    {
        $article = new Article;
        $article->body = 'See: https://vimeo.com/123456789';

        $this->assertStringContainsString('player.vimeo.com/video/123456789', $article->renderedBody());
    }

    public function test_rendered_body_preserves_plain_text(): void
    {
        $article = new Article;
        $article->body = 'Just some text with no links.';

        $this->assertSame('Just some text with no links.', $article->renderedBody());
    }

    public function test_rendered_body_handles_null(): void
    {
        $article = new Article;
        $article->body = null;

        $this->assertSame('', $article->renderedBody());
    }

    public function test_types_has_thirteen_entries(): void
    {
        $this->assertCount(13, Article::TYPES);
    }

    public function test_each_type_has_icon_color_label(): void
    {
        foreach (Article::TYPES as $key => $meta) {
            $this->assertArrayHasKey('icon', $meta, "Type '{$key}' missing 'icon'");
            $this->assertArrayHasKey('color', $meta, "Type '{$key}' missing 'color'");
            $this->assertArrayHasKey('label', $meta, "Type '{$key}' missing 'label'");
        }
    }

    public function test_can_be_edited_by_bureau_master(): void
    {
        $article = new Article(['article_type' => 'news', 'author_id' => 99]);
        $user = $this->createMockUser(isBureauMaster: true, id: 1);

        $this->assertTrue($article->canBeEditedBy($user));
    }

    public function test_classified_can_be_edited_by_author(): void
    {
        $article = new Article(['article_type' => 'classified', 'author_id' => 5]);
        $user = $this->createMockUser(isBureauMaster: false, id: 5);

        $this->assertTrue($article->canBeEditedBy($user));
    }

    public function test_classified_cannot_be_edited_by_other_member(): void
    {
        $article = new Article(['article_type' => 'classified', 'author_id' => 5]);
        $user = $this->createMockUser(isBureauMaster: false, id: 99);

        $this->assertFalse($article->canBeEditedBy($user));
    }

    public function test_news_cannot_be_edited_by_regular_member(): void
    {
        $article = new Article(['article_type' => 'news', 'author_id' => 5]);
        $user = $this->createMockUser(isBureauMaster: false, id: 5);

        $this->assertFalse($article->canBeEditedBy($user));
    }

    private function createMockUser(bool $isBureauMaster, int $id): object
    {
        return new class($isBureauMaster, $id)
        {
            public function __construct(private bool $bureauMaster, public int $id) {}

            public function can(string $ability): bool
            {
                return $this->bureauMaster;
            }

            public function isBureauMaster(): bool
            {
                return $this->bureauMaster;
            }
        };
    }
}
