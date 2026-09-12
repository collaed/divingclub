<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\BuddyRequest;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class HomeFeedWidgetsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function member(string $email): User
    {
        $user = User::create([
            'username' => 'm'.uniqid(),
            'primary_email' => $email,
            'password' => 'Password1',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Freddy', 'last_name' => 'Diver']);

        return $user;
    }

    public function test_member_home_shows_compact_classifieds_and_buddy_widgets(): void
    {
        $poster = $this->member('poster@example.com');

        Article::create([
            'title' => 'UNIQUE_TANK_FOR_SALE_XYZ',
            'body' => '<p>Barely used.</p>',
            'slug' => 'unique-tank-for-sale-xyz',
            'article_type' => 'classified',
            'author_id' => $poster->id,
            'is_published' => true,
            'is_public' => false,
        ]);

        BuddyRequest::create([
            'user_id' => $poster->id,
            'location_text' => 'UNIQUE_DIVE_SPOT_XYZ',
            'dive_date' => now()->addDays(3),
            'need_type' => 'buddy',
            'is_active' => true,
        ]);

        $viewer = $this->member('viewer@example.com');

        $this->actingAs($viewer)->get('/')
            ->assertOk()
            ->assertSee('UNIQUE_TANK_FOR_SALE_XYZ')
            ->assertSee('UNIQUE_DIVE_SPOT_XYZ')
            ->assertSee('width:72px;height:72px', false);
    }

    public function test_expired_classified_and_past_buddy_request_are_not_shown(): void
    {
        $poster = $this->member('poster2@example.com');

        Article::create([
            'title' => 'STALE_CLASSIFIED_XYZ',
            'body' => '<p>Old.</p>',
            'slug' => 'stale-classified-xyz',
            'article_type' => 'classified',
            'author_id' => $poster->id,
            'is_published' => true,
            'is_public' => false,
            'expires_at' => now()->subDay(),
        ]);

        BuddyRequest::create([
            'user_id' => $poster->id,
            'location_text' => 'STALE_DIVE_SPOT_XYZ',
            'dive_date' => now()->subDay(),
            'need_type' => 'buddy',
            'is_active' => true,
        ]);

        $viewer = $this->member('viewer2@example.com');

        $this->actingAs($viewer)->get('/')
            ->assertOk()
            ->assertDontSee('STALE_CLASSIFIED_XYZ')
            ->assertDontSee('STALE_DIVE_SPOT_XYZ');
    }
}
