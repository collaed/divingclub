<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ClassifiedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
    }

    public function test_classifieds_page_loads(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get('/classifieds')->assertOk();
    }

    public function test_guest_cannot_access_classifieds(): void
    {
        $this->get('/classifieds')->assertRedirect('/login');
    }

    public function test_member_can_create_classified(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->post('/classifieds', [
            'title' => 'Selling my BCD',
            'body' => '<p>Good condition, size M</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', [
            'title' => 'Selling my BCD',
            'article_type' => 'classified',
            'author_id' => $user->id,
        ]);
    }

    public function test_member_can_edit_own_classified(): void
    {
        $user = $this->createUser();
        $article = Article::create([
            'title' => 'My Ad',
            'slug' => 'my-ad',
            'body' => '<p>Original</p>',
            'article_type' => 'classified',
            'is_published' => true,
            'author_id' => $user->id,
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->put("/classifieds/{$article->id}", [
            'title' => 'Updated Ad',
            'body' => '<p>Updated</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'title' => 'Updated Ad']);
    }

    public function test_member_cannot_edit_others_classified(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $article = Article::create([
            'title' => 'Not yours',
            'slug' => 'not-yours',
            'body' => '<p>Content</p>',
            'article_type' => 'classified',
            'is_published' => true,
            'author_id' => $owner->id,
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($other)->put("/classifieds/{$article->id}", [
            'title' => 'Hacked',
            'body' => '<p>Hacked</p>',
        ])->assertForbidden();
    }

    public function test_member_can_delete_own_classified(): void
    {
        $user = $this->createUser();
        $article = Article::create([
            'title' => 'Delete me',
            'slug' => 'delete-me',
            'body' => '<p>Content</p>',
            'article_type' => 'classified',
            'is_published' => true,
            'author_id' => $user->id,
            'published_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->delete("/classifieds/{$article->id}")->assertRedirect();
        $this->assertDatabaseMissing('articles', ['id' => $article->id, 'deleted_at' => null]);
    }

    private function createUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id')
            ?? DB::table($roleTable)->where('name', 'member')->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
