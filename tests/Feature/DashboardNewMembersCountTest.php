<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class DashboardNewMembersCountTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_new_members_this_year_counts_adhesion_year_not_row_creation(): void
    {
        // Whole roster created "now" (import artefact), joined in different years.
        $this->makeMember((int) date('Y'));
        $this->makeMember((int) date('Y'));
        $this->makeMember((int) date('Y') - 3);
        $this->makeMember(null);

        $bureau = $this->createBureauUser(); // also created "now"

        $view = $this->actingAs($bureau)->get('/admin/dashboard')->assertOk()->viewData('stats');

        $this->assertSame(2, $view['new_members_this_year']);
        $this->assertGreaterThan($view['new_members_this_year'], $view['total_members']);
    }

    private function makeMember(?int $adhesionYear): User
    {
        $u = User::create([
            'primary_email' => 'm'.uniqid().'@example.com',
            'password' => 'Password1!',
            'role_id' => 2,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E', 'adhesion_year' => $adhesionYear]);

        return $u;
    }
}
