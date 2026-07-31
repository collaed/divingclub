<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class AdminNewsletterTest extends TestCase
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

    public function test_bureau_can_list_newsletters(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.newsletters.index'))
            ->assertOk();
    }

    public function test_bureau_can_create_newsletter(): void
    {
        $article = Article::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.newsletters.store'), [
                'title' => 'July 2026 Newsletter',
                'month' => '2026-07',
                'slots' => [
                    ['position' => 1, 'article_id' => $article->id],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('newsletters', ['title' => 'July 2026 Newsletter']);
    }

    public function test_sent_newsletter_cannot_be_updated(): void
    {
        $newsletter = Newsletter::factory()->sent()->create();
        $article = Article::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.newsletters.update', $newsletter), [
                'title' => 'Changed',
                'month' => '2026-07',
                'slots' => [['position' => 1, 'article_id' => $article->id]],
            ])
            ->assertForbidden();
    }
}
