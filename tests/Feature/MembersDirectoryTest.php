<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class MembersDirectoryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_former_members_are_excluded_by_default(): void
    {
        $current = $this->createMemberUser();
        $former = $this->createMemberUser();
        $formerStatus = MemberStatus::create(['name' => 'Former', 'slug' => 'former']);
        $former->update(['status_id' => $formerStatus->id]);

        $response = $this->actingAs($current)->get(route('members.directory'));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $current->id));
        $this->assertFalse($response->viewData('members')->contains('id', $former->id));
    }

    public function test_a_crafted_status_id_cannot_reveal_former_members(): void
    {
        $current = $this->createMemberUser();
        $former = $this->createMemberUser();
        $formerStatus = MemberStatus::create(['name' => 'Former', 'slug' => 'former']);
        $former->update(['status_id' => $formerStatus->id]);

        $response = $this->actingAs($current)->get(route('members.directory', ['status' => $formerStatus->id]));

        $response->assertOk();
        $this->assertFalse($response->viewData('members')->contains('id', $former->id));
    }

    public function test_the_status_filter_never_offers_the_former_status(): void
    {
        MemberStatus::create(['name' => 'Former', 'slug' => 'former']);

        $response = $this->actingAs($this->createMemberUser())->get(route('members.directory'));

        $response->assertOk();
        $this->assertFalse($response->viewData('statuses')->contains('slug', 'former'));
    }

    public function test_trombinoscope_excludes_former_members_too(): void
    {
        $current = $this->createMemberUser();
        $current->detail->update(['avatar_path' => 'avatars/current.jpg']);
        $former = $this->createMemberUser();
        $formerStatus = MemberStatus::create(['name' => 'Former', 'slug' => 'former']);
        $former->update(['status_id' => $formerStatus->id]);
        $former->detail->update(['avatar_path' => 'avatars/former.jpg']);

        $response = $this->actingAs($current)->get(route('members.trombinoscope'));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $current->id));
        $this->assertFalse($response->viewData('members')->contains('id', $former->id));
    }
}
