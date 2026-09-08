<?php

namespace Tests\Feature;

use App\Helpers\SystemContent;
use App\Models\Article;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class SystemArticlesNotInFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SpatieRole::findOrCreate('member', 'web');
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
    }

    public function test_excluding_system_scope_drops_sys_slugged_articles(): void
    {
        Article::create(['title' => 'Real News', 'slug' => 'real-news', 'body' => '<p>x</p>', 'article_type' => 'news', 'is_published' => true, 'is_public' => true]);
        SystemContent::ensure(SystemContent::DUES_FOOTER, 'Dues footer', '<p>owed</p>');

        $slugs = Article::excludingSystem()->pluck('slug');

        $this->assertContains('real-news', $slugs);
        $this->assertNotContains(SystemContent::DUES_FOOTER, $slugs);
    }

    public function test_home4_recent_articles_hides_system_content(): void
    {
        Article::create(['title' => 'GenuineNewsZZ', 'slug' => 'genuine-news-zz', 'body' => '<p>x</p>', 'article_type' => 'news', 'is_published' => true, 'is_public' => true]);
        SystemContent::ensure(SystemContent::HOME_LANDING, 'HiddenSystemZZ', '<p>welcome</p>');

        $user = User::create([
            'primary_email' => 'm'.uniqid().'@example.com', 'password' => 'Password1!',
            'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'M', 'last_name' => 'E']);

        $this->actingAs($user)->get('/home4')
            ->assertOk()
            ->assertSee('GenuineNewsZZ')
            ->assertDontSee('HiddenSystemZZ');
    }
}
