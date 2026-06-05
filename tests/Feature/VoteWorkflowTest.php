<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\MemberStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteBallot;
use App\Models\VoteOption;
use App\Models\VoteToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

#[Group('p1')]
class VoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::upsert([['id' => 2, 'name' => 'Member', 'slug' => 'member'], ['id' => 6, 'name' => 'Bureau Master', 'slug' => 'bureau_master']], ['id']);
        foreach (['member', 'bureau_master'] as $r) {
            SpatieRole::findOrCreate($r, 'web');
        }
        MemberStatus::upsert([['id' => 1, 'name' => 'Active', 'slug' => 'active']], ['id']);
    }

    private function admin(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 6, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('bureau_master');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'A', 'last_name' => 'D']);

        return $u;
    }

    private function member(): User
    {
        $u = User::create(['primary_email' => fake()->unique()->safeEmail(), 'password' => 'P', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now()]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'M', 'last_name' => 'E']);

        return $u;
    }

    private function openVote(string $mode = 'simple'): Vote
    {
        $creator = User::first() ?? $this->member();
        $vote = Vote::create(['title' => 'Test Vote', 'mode' => $mode, 'status' => 'open', 'allow_change' => $mode === 'simple', 'allow_multiple' => false, 'created_by' => $creator->id]);
        VoteOption::create(['vote_id' => $vote->id, 'label' => 'Option A']);
        VoteOption::create(['vote_id' => $vote->id, 'label' => 'Option B']);

        return $vote;
    }

    public function test_simple_vote_cast(): void
    {
        $vote = $this->openVote();
        $member = $this->member();
        $token = VoteToken::create(['vote_id' => $vote->id, 'user_id' => $member->id, 'token' => 'test-token-123']);
        $optionA = $vote->options->first();

        $this->post('/vote/test-token-123', ['option_id' => $optionA->id])->assertSessionHas('success');
        $this->assertDatabaseHas('vote_ballots', ['vote_id' => $vote->id, 'vote_option_id' => $optionA->id]);
    }

    public function test_simple_vote_can_change(): void
    {
        $vote = $this->openVote();
        $member = $this->member();
        VoteToken::create(['vote_id' => $vote->id, 'user_id' => $member->id, 'token' => 'change-token']);
        $options = $vote->options;

        $this->post('/vote/change-token', ['option_id' => $options[0]->id]);
        $this->post('/vote/change-token', ['option_id' => $options[1]->id]);

        $this->assertEquals(1, VoteBallot::where('vote_id', $vote->id)->count());
        $this->assertDatabaseHas('vote_ballots', ['vote_option_id' => $options[1]->id]);
    }

    public function test_election_vote_is_irreversible(): void
    {
        $vote = $this->openVote('election');
        $member = $this->member();
        VoteToken::create(['vote_id' => $vote->id, 'user_id' => $member->id, 'token' => 'election-token']);
        $optionA = $vote->options->first();

        $this->post('/vote/election-token', ['option_id' => $optionA->id]);
        $this->post('/vote/election-token', ['option_id' => $optionA->id])->assertSessionHas('error');
    }

    public function test_election_ballot_is_anonymous(): void
    {
        $vote = $this->openVote('election');
        $member = $this->member();
        VoteToken::create(['vote_id' => $vote->id, 'user_id' => $member->id, 'token' => 'anon-token']);

        $this->post('/vote/anon-token', ['option_id' => $vote->options->first()->id]);

        $ballot = VoteBallot::where('vote_id', $vote->id)->first();
        $this->assertNull($ballot->token_hash);
    }

    public function test_closed_vote_rejects_ballot(): void
    {
        $vote = $this->openVote();
        $vote->update(['status' => 'closed']);
        $member = $this->member();
        VoteToken::create(['vote_id' => $vote->id, 'user_id' => $member->id, 'token' => 'closed-token']);

        $this->post('/vote/closed-token', ['option_id' => $vote->options->first()->id])->assertSessionHas('error');
        $this->assertEquals(0, VoteBallot::where('vote_id', $vote->id)->count());
    }

    public function test_token_generation(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $vote = Vote::create(['title' => 'Token Test', 'mode' => 'simple', 'status' => 'open', 'allow_change' => true, 'allow_multiple' => false, 'created_by' => $admin->id]);
        VoteOption::create(['vote_id' => $vote->id, 'label' => 'Yes']);

        $this->actingAs($admin)->post("/admin/votes/{$vote->id}/tokens");

        // Both admin and member should get tokens (both verified)
        $this->assertTrue(VoteToken::where('vote_id', $vote->id)->where('user_id', $member->id)->exists());
    }
}
