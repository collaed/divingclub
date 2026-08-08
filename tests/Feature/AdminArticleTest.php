<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminArticleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('bureau_master');
    }

    public function test_bureau_can_list_articles(): void
    {
        Article::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.articles.index'))
            ->assertOk();
    }

    public function test_bureau_can_create_article(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), [
                'title' => 'Test Article',
                'slug' => 'test-article-'.uniqid(),
                'body' => '<p>Test content.</p>',
                'article_type' => 'news',
                'is_published' => true,
                'is_public' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('articles', ['title' => 'Test Article']);
    }

    public function test_bureau_can_update_article(): void
    {
        $article = Article::create([
            'title' => 'Original', 'slug' => 'original-'.uniqid(),
            'body' => '<p>Body.</p>', 'article_type' => 'news',
            'is_published' => true, 'author_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.articles.update', $article), [
                'title' => 'Updated Title',
                'slug' => $article->slug,
                'body' => '<p>Updated.</p>',
                'article_type' => 'news',
                'is_published' => true,
                'is_public' => false,
            ])
            ->assertRedirect();

        $this->assertEquals('Updated Title', $article->fresh()->title);
    }

    public function test_bureau_can_delete_article(): void
    {
        $article = Article::create([
            'title' => 'Delete Me', 'slug' => 'delete-me-'.uniqid(),
            'body' => '<p>Delete.</p>', 'article_type' => 'news',
            'is_published' => true, 'author_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.articles.destroy', $article))
            ->assertRedirect();

        // Article uses soft-delete
        $this->assertNull(Article::find($article->id));
    }
}
