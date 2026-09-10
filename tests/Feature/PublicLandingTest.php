<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\SystemContent;
use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\SystemContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

    public function test_landing_hides_cancelled_upcoming_events(): void
    {
        Event::factory()->create([
            'title' => 'Live Pool Session',
            'event_date' => now()->addWeek(),
            'status' => 'scheduled',
        ]);
        Event::factory()->create([
            'title' => 'Scrapped Apnea Night',
            'event_date' => now()->addWeek(),
            'status' => 'cancelled',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Live Pool Session', false)
            ->assertDontSee('Scrapped Apnea Night', false);
    }

    public function test_landing_member_count_counts_only_current_members(): void
    {
        Carbon::setTestNow('2026-10-15'); // season year 2027, calendar year 2026
        Cache::flush();

        foreach ([[2027], [2026], [2025, 2026]] as $years) {
            $u = User::factory()->create(['email_verified_at' => now()]);
            $u->assignRole('member');
            MemberDetail::create(['user_id' => $u->id, 'first_name' => 'A', 'last_name' => 'B', 'cotisation_years' => $years]);
        }
        // Lapsed — last paid 2023, so neither the season nor the calendar year.
        $lapsed = User::factory()->create(['email_verified_at' => now()]);
        $lapsed->assignRole('member');
        MemberDetail::create(['user_id' => $lapsed->id, 'first_name' => 'C', 'last_name' => 'D', 'cotisation_years' => [2022, 2023]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-target="3"', false);

        Carbon::setTestNow();
    public function test_returning_guest_gets_the_widget_home_not_the_landing(): void
    {
        $this->withUnencryptedCookie('cep_seen_landing', (string) time())
            ->get('/')
            ->assertOk()
            ->assertDontSee('h3-hero', false)
            ->assertSee('zone-top', false);
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
