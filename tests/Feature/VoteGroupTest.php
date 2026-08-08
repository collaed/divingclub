<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteGroup;
use App\Models\VoteOption;
use App\Models\VoteToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class VoteGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 6, 'name' => 'bureau_master', 'slug' => 'bureau_master']);
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 11, 'name' => 'Former', 'slug' => 'former']);
        SpatieRole::findOrCreate('bureau_master', 'web');
        SpatieRole::findOrCreate('member', 'web');
    }

    public function test_admin_can_create_vote_group(): void
    {
        $admin = $this->createUser(6, 'bureau_master');

        $response = $this->actingAs($admin)->post(route('admin.vote-groups.store'), [
            'title' => 'AG 2026',
            'description' => 'Assemblée Générale',
            'opens_at' => null,
            'closes_at' => null,
            'questions' => [
                ['title' => 'Approve accounts', 'mode' => 'simple', 'num_positions' => 1, 'options' => ['Yes', 'No', 'Abstain']],
                ['title' => 'Elect board', 'mode' => 'election', 'num_positions' => 3, 'options' => ['Alice', 'Bob', 'Charlie', 'Dave']],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vote_groups', ['title' => 'AG 2026']);
        $group = VoteGroup::where('title', 'AG 2026')->first();
        $this->assertCount(2, $group->votes);
        $this->assertCount(3, $group->votes->first()->options);
        $this->assertCount(4, $group->votes->last()->options);
    }

    public function test_generate_tokens_excludes_former_members(): void
    {
        $admin = $this->createUser(6, 'bureau_master');
        $active = $this->createUser(2, 'member', 1);
        $former = $this->createUser(2, 'member', 11);

        $group = VoteGroup::create(['title' => 'Test', 'status' => 'draft', 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('admin.vote-groups.generate-tokens', $group));

        // Admin + active get tokens, former does not
        $this->assertTrue($group->tokens()->where('user_id', $admin->id)->exists());
        $this->assertTrue($group->tokens()->where('user_id', $active->id)->exists());
        $this->assertFalse($group->tokens()->where('user_id', $former->id)->exists());
    }

    public function test_voter_can_cast_grouped_vote(): void
    {
        $admin = $this->createUser(6, 'bureau_master');
        $group = VoteGroup::create(['title' => 'AG', 'status' => 'open', 'created_by' => $admin->id]);

        $vote1 = Vote::create(['vote_group_id' => $group->id, 'title' => 'Q1', 'mode' => 'simple', 'status' => 'open', 'created_by' => $admin->id]);
        $opt1a = VoteOption::create(['vote_id' => $vote1->id, 'label' => 'Yes', 'sort_order' => 0]);
        $opt1b = VoteOption::create(['vote_id' => $vote1->id, 'label' => 'No', 'sort_order' => 1]);

        $vote2 = Vote::create(['vote_group_id' => $group->id, 'title' => 'Q2', 'mode' => 'election', 'num_positions' => 2, 'allow_multiple' => true, 'status' => 'open', 'created_by' => $admin->id]);
        $opt2a = VoteOption::create(['vote_id' => $vote2->id, 'label' => 'A', 'sort_order' => 0]);
        $opt2b = VoteOption::create(['vote_id' => $vote2->id, 'label' => 'B', 'sort_order' => 1]);
        $opt2c = VoteOption::create(['vote_id' => $vote2->id, 'label' => 'C', 'sort_order' => 2]);

        $token = VoteToken::create(['vote_group_id' => $group->id, 'user_id' => $admin->id, 'token' => Str::random(128)]);

        $response = $this->post(route('vote-group.cast', $token->token), [
            'votes' => [
                $vote1->id => [$opt1a->id],
                $vote2->id => [$opt2a->id, $opt2c->id],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vote_ballots', ['vote_id' => $vote1->id, 'vote_option_id' => $opt1a->id]);
        $this->assertDatabaseHas('vote_ballots', ['vote_id' => $vote2->id, 'vote_option_id' => $opt2a->id]);
        $this->assertDatabaseHas('vote_ballots', ['vote_id' => $vote2->id, 'vote_option_id' => $opt2c->id]);
        $this->assertTrue($token->fresh()->is_consumed);
    }

    public function test_election_rejects_too_many_selections(): void
    {
        $admin = $this->createUser(6, 'bureau_master');
        $group = VoteGroup::create(['title' => 'AG', 'status' => 'open', 'created_by' => $admin->id]);
        $vote = Vote::create(['vote_group_id' => $group->id, 'title' => 'Q', 'mode' => 'election', 'num_positions' => 1, 'allow_multiple' => true, 'status' => 'open', 'created_by' => $admin->id]);
        $o1 = VoteOption::create(['vote_id' => $vote->id, 'label' => 'A', 'sort_order' => 0]);
        $o2 = VoteOption::create(['vote_id' => $vote->id, 'label' => 'B', 'sort_order' => 1]);
        $token = VoteToken::create(['vote_group_id' => $group->id, 'user_id' => $admin->id, 'token' => Str::random(128)]);

        $response = $this->post(route('vote-group.cast', $token->token), [
            'votes' => [$vote->id => [$o1->id, $o2->id]],
        ]);

        $response->assertSessionHasErrors();
    }

    private function createUser(int $roleId, string $roleName, int $statusId = 1): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $actualRoleId = DB::table($roleTable)->where('id', $roleId)->value('id') ?? $roleId;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $actualRoleId,
            'status_id' => $statusId,
            'email_verified_at' => now(),
        ]);
        $u->assignRole($roleName);
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => fake()->lastName()]);

        return $u;
    }
}
