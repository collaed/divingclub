<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class AdminMembersPerPageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        for ($i = 0; $i < 5; $i++) {
            $u = User::create([
                'primary_email' => 'm'.uniqid().'@example.com', 'password' => 'x',
                'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
            ]);
            $u->assignRole('member');
            MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E']);
        }
    }

    public function test_selector_default_matches_the_actual_page_size(): void
    {
        $response = $this->actingAs($this->createBureauUser())->get('/admin/members')->assertOk();

        $this->assertSame(25, $response->viewData('members')->perPage());

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/<option value="25"[^>]*selected/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="30"[^>]*selected/', $html);
    }

    public function test_selector_honours_an_explicit_per_page(): void
    {
        $response = $this->actingAs($this->createBureauUser())->get('/admin/members?per_page=50')->assertOk();

        $this->assertSame(50, $response->viewData('members')->perPage());
        $this->assertMatchesRegularExpression('/<option value="50"[^>]*selected/', $response->getContent());
    }
}
