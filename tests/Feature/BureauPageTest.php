<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BureauPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.locale' => 'fr']); // skip the auto-translate branch

        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
    }

    private function bureauMember(string $first, string $last, ?string $avatar): void
    {
        $u = User::factory()->create();
        MemberDetail::factory()->create([
            'user_id' => $u->id,
            'first_name' => $first,
            'last_name' => $last,
            'bureau_member' => true,
            'avatar_path' => $avatar,
        ]);
    }

    private function bureauArticle(): void
    {
        Article::factory()->public()->create([
            'slug' => 'bureau',
            'body' => '<h4>Le Bureau</h4><p>intro</p>'
                .'<div class="row g-3 text-center">'
                .'<div class="col-6 col-md-4 mb-3"><img src="/storage/avatars/STALE99.png" class="rounded-circle" style="width:100px;height:100px;object-fit:cover"><h6 class="mt-2 mb-0"><a href="/members">Old Name</a></h6></div>'
                .'<div class="col-6 col-md-4 mb-3"><div class="rounded-circle mx-auto d-flex align-items-center justify-content-center text-white fw-bold" style="width:100px;height:100px;background:#1a237e;font-size:1.5rem">XX</div><h6 class="mt-2 mb-0"><a href="/members">Gone Person</a></h6></div>'
                .'</div>'
                .'<h5>Adresse</h5><p>10 rue Benjamin Franklin</p>',
        ]);
    }

    public function test_bureau_page_lists_members_live_and_strips_the_stale_grid(): void
    {
        $this->bureauArticle();
        $this->bureauMember('Anne', 'Martin', 'avatars/anne.jpg');
        $this->bureauMember('Bruno', 'Zola', null);

        // a non-bureau member must not leak onto the public page
        $other = User::factory()->create();
        MemberDetail::factory()->create(['user_id' => $other->id, 'first_name' => 'Carla', 'last_name' => 'Nobody', 'bureau_member' => false]);

        $res = $this->get('/article/bureau');

        $res->assertOk();
        $res->assertSee('Anne Martin');
        $res->assertSee('Bruno Zola');
        $res->assertSee('BZ', false);                    // initials fallback, no avatar
        $res->assertSee('storage/avatars/anne.jpg', false);
        $res->assertSee('Adresse');                       // rest of the article body kept
        $res->assertDontSee('STALE99.png', false);        // legacy grid removed
        $res->assertDontSee('Old Name');
        $res->assertDontSee('Carla Nobody');
    }

    public function test_grid_markup_is_untouched_on_other_article_slugs(): void
    {
        Article::factory()->public()->create([
            'slug' => 'values',
            'body' => '<div class="row g-3 text-center"><div class="col">keep me</div></div>',
        ]);

        $this->get('/article/values')->assertOk()->assertSee('keep me');
    }
}
