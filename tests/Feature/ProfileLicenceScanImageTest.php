<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Federation;
use App\Models\MemberLicence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class ProfileLicenceScanImageTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('local');
    }

    private function licenceWithScan(int $userId): MemberLicence
    {
        $fed = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'FLASSA', 'visibility' => 'active']);
        Storage::disk('local')->put('licence-scans/1.png', 'fake-png-bytes');

        return MemberLicence::create(['user_id' => $userId, 'federation_id' => $fed->id, 'licence_number' => 'LS-0001', 'scan_image_path' => 'licence-scans/1.png']);
    }

    public function test_the_licence_holder_can_view_their_own_scan(): void
    {
        $member = $this->createMemberUser();
        $licence = $this->licenceWithScan($member->id);

        $this->actingAs($member)->get(route('profile.licence.scan', $licence))->assertOk();
    }

    public function test_bureau_can_view_any_members_scan(): void
    {
        $member = $this->createMemberUser();
        $licence = $this->licenceWithScan($member->id);

        $this->actingAs($this->createBureauUser())->get(route('profile.licence.scan', $licence))->assertOk();
    }

    public function test_a_different_member_is_forbidden(): void
    {
        $member = $this->createMemberUser();
        $licence = $this->licenceWithScan($member->id);

        $this->actingAs($this->createMemberUser())->get(route('profile.licence.scan', $licence))->assertForbidden();
    }

    public function test_404_when_the_licence_has_no_scan(): void
    {
        $member = $this->createMemberUser();
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        $licence = MemberLicence::create(['user_id' => $member->id, 'federation_id' => $fed->id, 'licence_number' => 'X-01-0001']);

        $this->actingAs($member)->get(route('profile.licence.scan', $licence))->assertNotFound();
    }
}
