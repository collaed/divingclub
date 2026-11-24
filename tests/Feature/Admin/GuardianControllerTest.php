<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\GuardianLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class GuardianControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function minorWithGuardian(): User
    {
        $minor = $this->createMemberUser();
        $minor->detail->update(['date_of_birth' => now()->subYears(10)]);
        $guardian = $this->createMemberUser();
        GuardianLink::create([
            'minor_user_id' => $minor->id,
            'guardian_user_id' => $guardian->id,
            'relationship' => 'parent',
        ]);

        return $minor;
    }

    public function test_index_renders_a_working_unlink_link_for_a_minor_with_a_guardian(): void
    {
        $minor = $this->minorWithGuardian();
        $link = GuardianLink::where('minor_user_id', $minor->id)->firstOrFail();

        $response = $this->actingAs($this->createBureauUser())->get(route('admin.guardians.index'));

        $response->assertOk();
        $response->assertSee(route('admin.guardians.unlink', $link->id), false);
    }

    public function test_unlink_removes_the_guardian_link(): void
    {
        $minor = $this->minorWithGuardian();
        $link = GuardianLink::where('minor_user_id', $minor->id)->firstOrFail();

        $this->actingAs($this->createBureauUser())
            ->delete(route('admin.guardians.unlink', $link->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('guardian_links', ['id' => $link->id]);
    }
}
