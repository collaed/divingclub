<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class ClassifiedsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member']], ['id']);
        SpatieRole::findOrCreate('member', 'web');
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function member(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E']);

        return $u;
    }

    public function test_member_can_create_classified(): void
    {
        $u = $this->member();

        $this->actingAs($u)->post('/classifieds', [
            'title' => 'BCD for sale',
            'body' => '<p>Good condition Mares BCD, size M</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['title' => 'BCD for sale', 'article_type' => 'classified', 'author_id' => $u->id]);
    }

    public function test_classified_has_30_day_expiry(): void
    {
        $u = $this->member();

        $this->actingAs($u)->post('/classifieds', [
            'title' => 'Fins for sale',
            'body' => '<p>Mares fins</p>',
        ]);

        $ad = Article::where('title', 'Fins for sale')->first();
        $this->assertNotNull($ad->expires_at);
        $this->assertTrue($ad->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_owner_can_delete_classified(): void
    {
        $u = $this->member();
        $ad = Article::create(['title' => 'Test', 'slug' => 'test-'.uniqid(), 'body' => 'x', 'article_type' => 'classified', 'author_id' => $u->id, 'expires_at' => now()->addDays(30)]);

        $this->actingAs($u)->delete("/classifieds/{$ad->id}")->assertRedirect();
        $this->assertSoftDeleted('articles', ['id' => $ad->id]);
    }

    public function test_other_member_cannot_delete_classified(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $ad = Article::create(['title' => 'Test', 'slug' => 'test-'.uniqid(), 'body' => 'x', 'article_type' => 'classified', 'author_id' => $owner->id, 'expires_at' => now()->addDays(30)]);

        $this->actingAs($other)->delete("/classifieds/{$ad->id}")->assertStatus(403);
    }

    public function test_classifieds_page_loads(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get('/classifieds')->assertOk();
    }
}
