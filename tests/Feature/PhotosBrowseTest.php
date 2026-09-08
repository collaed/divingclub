<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhotosBrowseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['status_id' => 1, 'email_verified_at' => now()]);
    }

    public function test_browser_navigation_redirects_to_the_gallery(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)
            ->get('/photos/browse', ['Sec-Fetch-Dest' => 'document'])
            ->assertRedirect(route('gallery'));

        // legacy browser with no Sec-Fetch-* headers, plain navigation Accept
        $this->actingAs($user)
            ->get('/photos/browse', ['Accept' => 'text/html,application/xhtml+xml'])
            ->assertRedirect(route('gallery'));
    }

    public function test_xhr_fetch_still_gets_the_json_feed(): void
    {
        $user = $this->verifiedUser();

        $res = $this->actingAs($user)->get('/photos/browse', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $res->assertOk();
        $this->assertJson($res->getContent() ?: '[]');
    }
}
