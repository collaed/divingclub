<?php

namespace Tests\Unit;

use App\Models\DiveGroupRule;
use Tests\TestCase;

class DiveGroupRuleTest extends TestCase
{
    public function test_matches_diver_no_cert(): void
    {
        $rule = new DiveGroupRule(['diver_condition' => 'no_cert']);

        $this->assertTrue($rule->matchesDiver(0));
        $this->assertTrue($rule->matchesDiver(null));
        $this->assertFalse($rule->matchesDiver(30));
    }

    public function test_matches_diver_any(): void
    {
        $rule = new DiveGroupRule(['diver_condition' => 'any']);

        $this->assertTrue($rule->matchesDiver(0));
        $this->assertTrue($rule->matchesDiver(50));
        $this->assertTrue($rule->matchesDiver(null));
    }

    public function test_matches_diver_max_rank(): void
    {
        $rule = new DiveGroupRule(['diver_condition' => 'max_rank:30']);

        $this->assertTrue($rule->matchesDiver(20));
        $this->assertTrue($rule->matchesDiver(30));
        $this->assertFalse($rule->matchesDiver(31));
    }

    public function test_matches_diver_min_rank(): void
    {
        $rule = new DiveGroupRule(['diver_condition' => 'min_rank:30']);

        $this->assertFalse($rule->matchesDiver(20));
        $this->assertTrue($rule->matchesDiver(30));
        $this->assertTrue($rule->matchesDiver(50));
    }

    public function test_matches_diver_unknown_condition_returns_false(): void
    {
        $rule = new DiveGroupRule(['diver_condition' => 'unknown']);

        $this->assertFalse($rule->matchesDiver(30));
    }

    public function test_leader_satisfied_with_sufficient_rank(): void
    {
        $rule = new DiveGroupRule(['min_leader_rank' => 70, 'leader_category' => null]);

        $this->assertTrue($rule->leaderSatisfied(70, 'instructor'));
        $this->assertTrue($rule->leaderSatisfied(80, 'diver'));
        $this->assertFalse($rule->leaderSatisfied(60, 'instructor'));
    }

    public function test_leader_satisfied_requires_instructor_category(): void
    {
        $rule = new DiveGroupRule(['min_leader_rank' => 70, 'leader_category' => 'instructor']);

        $this->assertTrue($rule->leaderSatisfied(70, 'instructor'));
        $this->assertFalse($rule->leaderSatisfied(70, 'diver'));
    }

    public function test_leader_satisfied_null_rank_returns_false(): void
    {
        $rule = new DiveGroupRule(['min_leader_rank' => 70, 'leader_category' => null]);

        $this->assertFalse($rule->leaderSatisfied(null, 'instructor'));
    }

    public function test_dive_modes_constant(): void
    {
        $this->assertSame(['supervised', 'autonomous', 'training', 'certification'], DiveGroupRule::DIVE_MODES);
    }
}
