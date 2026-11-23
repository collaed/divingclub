<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * LicenseService::memberCount() must enforce the cap against active members
 * only, not every member ever created — see tests/Unit/LicenseServiceTest.php
 * for the signature-verification unit tests, which don't touch the database.
 */
class LicenseServiceMemberCountTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_active_members_count_toward_the_license_cap(): void
    {
        $this->createMemberUser();
        $this->createMemberUser();

        $this->assertSame(2, LicenseService::memberCount());
    }

    public function test_former_members_do_not_count_toward_the_license_cap(): void
    {
        $former = MemberStatus::create(['name' => 'Former', 'slug' => 'former']);

        $this->createMemberUser();
        $leftTheClub = $this->createMemberUser();
        $leftTheClub->update(['status_id' => $former->id]);

        $this->assertSame(1, LicenseService::memberCount());
    }

    public function test_unclassified_members_do_not_count_toward_the_license_cap(): void
    {
        $spam = $this->createMemberUser();
        $spam->update(['status_id' => null]);

        $this->createMemberUser();

        $this->assertSame(1, LicenseService::memberCount());
    }
}
