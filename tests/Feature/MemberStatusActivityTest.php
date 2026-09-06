<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use App\Models\User;
use App\Services\MailAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class MemberStatusActivityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function makeMember(string $statusSlug, string $email): User
    {
        $status = MemberStatus::firstOrCreate(['slug' => $statusSlug], ['name' => ucfirst($statusSlug)]);

        return User::factory()->create([
            'primary_email' => $email,
            'status_id' => $status->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_former_status_is_the_only_inactive_status(): void
    {
        $this->assertSame(['former'], MemberStatus::inactiveSlugs());
    }

    public function test_honoraire_is_active(): void
    {
        $honoraire = MemberStatus::create(['name' => 'Honoraire', 'slug' => 'honoraire']);
        $this->assertTrue($honoraire->isActive());
    }

    public function test_former_is_not_active(): void
    {
        $former = MemberStatus::create(['name' => 'Ancien membre', 'slug' => 'former']);
        $this->assertFalse($former->isActive());
    }

    public function test_all_members_alias_excludes_former_but_includes_honoraire(): void
    {
        $this->makeMember('actif', 'actif@test.eu');
        $this->makeMember('honoraire', 'honoraire@test.eu');
        $this->makeMember('former', 'former@test.eu');

        $resolved = MailAliasService::resolve('members@clubcep.eu');

        $this->assertNotNull($resolved);
        $this->assertContains('actif@test.eu', $resolved['emails']);
        $this->assertContains('honoraire@test.eu', $resolved['emails']);
        $this->assertNotContains('former@test.eu', $resolved['emails']);
    }

    public function test_members_without_status_are_excluded_from_all_members(): void
    {
        User::factory()->create(['primary_email' => 'unclassified@test.eu', 'status_id' => null, 'email_verified_at' => now()]);
        $this->makeMember('actif', 'classified@test.eu');

        $resolved = MailAliasService::resolve('members@clubcep.eu');

        $this->assertNotNull($resolved);
        $this->assertContains('classified@test.eu', $resolved['emails']);
        $this->assertNotContains('unclassified@test.eu', $resolved['emails']);
    }
}
