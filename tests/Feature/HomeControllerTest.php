<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class HomeControllerTest extends TestCase
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

    public function test_homepage_loads_for_guest(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_home2_loads_for_guest(): void
    {
        $this->get('/home2')->assertOk();
    }

    public function test_home3_loads_for_guest(): void
    {
        $this->get('/home3')->assertOk();
    }

    public function test_home4_loads_for_authenticated_user(): void
    {
        $user = $this->createUser();
        $this->actingAs($user)->get('/home4')->assertOk();
    }

    public function test_article_show_loads(): void
    {
        $user = $this->createUser();
        $article = Article::create([
            'title' => 'Test Article',
            'slug' => 'test-article',
            'body' => '<p>Content</p>',
            'article_type' => 'news',
            'is_published' => true,
            'is_public' => true,
            'author_id' => $user->id,
        ]);

        $this->get('/article/test-article')->assertOk();
    }

    public function test_article_404_for_invalid_slug(): void
    {
        $this->get('/article/nonexistent-slug')->assertNotFound();
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
