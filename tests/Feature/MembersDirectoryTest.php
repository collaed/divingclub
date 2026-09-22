<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_directory_links_to_the_member_facing_profile_not_the_bureau_only_one(): void
    {
        $viewer = $this->createMemberUser();
        $other = $this->createMemberUser();

        $response = $this->actingAs($viewer)->get(route('members.directory'))->assertOk();

        // A non-bureau member must be able to actually follow the link —
        // admin.profile.show 403s anyone without a bureau role.
        $response->assertSee(route('members.profile', $other), false);
        $response->assertDontSee(route('admin.profile.show', $other), false);
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

    /** @return array<int, array{string, int}> option key => the age (in years) a member just inside it must be */
    public static function ageFilterCases(): array
    {
        return [
            'u12' => ['u12', 11], 'u14' => ['u14', 13], 'u16' => ['u16', 15], 'u18' => ['u18', 17], 'o18' => ['o18', 18],
        ];
    }

    #[DataProvider('ageFilterCases')]
    public function test_the_age_filter_includes_a_member_just_inside_the_threshold_and_excludes_one_just_outside(string $option, int $insideAge): void
    {
        $viewer = $this->createMemberUser();
        $inside = $this->createMemberUser();
        $inside->detail->update(['date_of_birth' => now()->subYears($insideAge)->subDay()]);
        $outsideAge = $option === 'o18' ? $insideAge - 1 : $insideAge + 2; // the next age that falls outside this option
        $outside = $this->createMemberUser();
        $outside->detail->update(['date_of_birth' => now()->subYears($outsideAge)->subDay()]);

        $response = $this->actingAs($viewer)->get(route('members.directory', ['age' => $option]));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $inside->id));
        $this->assertFalse($response->viewData('members')->contains('id', $outside->id));
    }

    public function test_an_unrecognised_age_option_is_ignored_not_errored(): void
    {
        $viewer = $this->createMemberUser();

        $this->actingAs($viewer)->get(route('members.directory', ['age' => 'not-a-real-option']))->assertOk();
    }

    public function test_the_level_filter_matches_either_the_scuba_or_the_apnea_field(): void
    {
        $viewer = $this->createMemberUser();
        $scuba = $this->createMemberUser();
        $scuba->detail->update(['certification_level' => 'N1']);
        $apnea = $this->createMemberUser();
        $apnea->detail->update(['apnea_level' => 'N1']);
        $other = $this->createMemberUser();
        $other->detail->update(['certification_level' => 'N2']);

        $response = $this->actingAs($viewer)->get(route('members.directory', ['level' => 'N1']));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $scuba->id));
        $this->assertTrue($response->viewData('members')->contains('id', $apnea->id));
        $this->assertFalse($response->viewData('members')->contains('id', $other->id));
    }

    public function test_the_level_options_list_is_the_union_of_both_fields_with_no_duplicates(): void
    {
        $viewer = $this->createMemberUser();
        $a = $this->createMemberUser();
        $a->detail->update(['certification_level' => 'N1']);
        $b = $this->createMemberUser();
        $b->detail->update(['apnea_level' => 'N1']); // same value as $a, in the other field — must appear once
        $c = $this->createMemberUser();
        $c->detail->update(['certification_level' => 'N2']);

        $response = $this->actingAs($viewer)->get(route('members.directory'));

        $response->assertOk();
        $levels = $response->viewData('levels');
        $this->assertSame(['N1', 'N2'], $levels->all());
    }
}
