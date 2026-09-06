<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\SystemContent;
use App\Models\MemberDetail;
use App\Models\User;
use Database\Seeders\SystemContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class PublicLandingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->seed(SystemContentSeeder::class);
    }

    public function test_guest_root_shows_landing_with_ctas(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('Try Diving'), false)
            ->assertSee('data-h3-login', false)          // login trigger present
            ->assertSee('cep_seen_landing', false)       // cookie/auto-dismiss logic present
            ->assertSee(route('trial.show'), false);
    }

    public function test_landing_renders_editable_article_body(): void
    {
        $article = SystemContent::article(SystemContent::HOME_LANDING);
        $article->update(['body' => '<p>UNIQUE_LANDING_MARKER_XYZ</p>', 'is_published' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('UNIQUE_LANDING_MARKER_XYZ', false)
            ->assertSee('h3-hero-lead', false);
    }

    public function test_authenticated_root_shows_dashboard_not_landing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'M', 'last_name' => 'N']);

        // The widget dashboard uses x-layout; the landing uses its own <html>
        // with the h3-hero markup. Assert we are NOT on the landing.
        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertDontSee('h3-hero', false);
    }
}
