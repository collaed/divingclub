<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CertificationLevel;
use App\Models\Federation;
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

    private function federation(): Federation
    {
        return Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
    }

    public function test_the_scuba_level_filter_matches_a_members_starred_cert(): void
    {
        $viewer = $this->createMemberUser();
        $fed = $this->federation();
        $n1 = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $n2 = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N2', 'name' => 'Niveau 2', 'category' => 'diver', 'rank' => 40]);

        $starred = $this->createMemberUser();
        $starred->certificationLevels()->attach($n1->id, ['is_primary' => true, 'display_priority' => 0]);
        $other = $this->createMemberUser();
        $other->certificationLevels()->attach($n2->id, ['is_primary' => true, 'display_priority' => 0]);

        $response = $this->actingAs($viewer)->get(route('members.directory', ['scuba_level_id' => $n1->id]));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $starred->id));
        $this->assertFalse($response->viewData('members')->contains('id', $other->id));
    }

    public function test_an_instructor_with_an_old_n1_does_not_show_under_the_n1_filter(): void
    {
        $viewer = $this->createMemberUser();
        $fed = $this->federation();
        $n1 = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $e1 = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'E1', 'name' => 'Initiateur', 'category' => 'instructor', 'rank' => 100]);

        $instructor = $this->createMemberUser();
        $instructor->certificationLevels()->attach($n1->id, ['display_priority' => 0]);
        $instructor->certificationLevels()->attach($e1->id, ['display_priority' => 0]);
        $instructor->recomputeCertificationStars();

        $response = $this->actingAs($viewer)->get(route('members.directory', ['scuba_level_id' => $n1->id]));

        $response->assertOk();
        $this->assertFalse($response->viewData('members')->contains('id', $instructor->id));
    }

    public function test_the_freediving_level_filter_is_independent_of_the_scuba_one(): void
    {
        $viewer = $this->createMemberUser();
        $fed = $this->federation();
        $n1 = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        $atp = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'ATP', 'name' => 'Pass Apnéiste', 'category' => 'diver', 'discipline' => 'freediving', 'rank' => 4]);

        $member = $this->createMemberUser();
        $member->certificationLevels()->attach($n1->id, ['is_primary' => true, 'display_priority' => 0]);
        $member->certificationLevels()->attach($atp->id, ['is_primary_freediving' => true, 'display_priority' => 0]);

        $response = $this->actingAs($viewer)->get(route('members.directory', ['freediving_level_id' => $atp->id]));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $member->id));
    }

    public function test_a_member_with_a_single_unstarred_scuba_cert_still_matches_the_filter(): void
    {
        $viewer = $this->createMemberUser();
        $fed = $this->federation();
        $n1 = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);

        $member = $this->createMemberUser();
        $member->certificationLevels()->attach($n1->id, ['display_priority' => 0]);

        $response = $this->actingAs($viewer)->get(route('members.directory', ['scuba_level_id' => $n1->id]));

        $response->assertOk();
        $this->assertTrue($response->viewData('members')->contains('id', $member->id));
    }

    public function test_the_level_options_are_grouped_by_federation(): void
    {
        $viewer = $this->createMemberUser();
        $fed = $this->federation();
        CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);
        CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'ATP', 'name' => 'Pass Apnéiste', 'category' => 'diver', 'discipline' => 'freediving', 'rank' => 4]);
        CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'NITROX', 'name' => 'Nitrox', 'category' => 'specialty', 'rank' => 200]);

        $response = $this->actingAs($viewer)->get(route('members.directory'));

        $response->assertOk();
        $scubaLevels = $response->viewData('scubaLevels');
        $freedivingLevels = $response->viewData('freedivingLevels');
        $this->assertTrue($scubaLevels->get('FFESSM')->contains('code', 'N1'));
        $this->assertFalse($scubaLevels->get('FFESSM')->contains('code', 'NITROX')); // specialty excluded
        $this->assertTrue($freedivingLevels->get('FFESSM')->contains('code', 'ATP'));
    }
}
