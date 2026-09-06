<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberStatus;
use App\Models\StatusSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class StatusSetTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_a_set_offers_its_statuses(): void
    {
        $fonctionnaire = MemberStatus::create(['name' => 'Fonctionnaire', 'slug' => 'fonctionnaire']);
        $sympathisant = MemberStatus::create(['name' => 'Sympathisant', 'slug' => 'sympathisant']);
        $former = MemberStatus::create(['name' => 'Ancien', 'slug' => 'former']);

        $set = StatusSet::create(['name' => 'Fonctionnaire / Membre de droit', 'slug' => 'fonctionnaire']);
        $set->statuses()->attach([
            $fonctionnaire->id => ['is_default' => true],
            $sympathisant->id => ['is_default' => false],
            $former->id => ['is_default' => false],
        ]);

        $slugs = $set->statuses->pluck('slug')->all();
        $this->assertContains('fonctionnaire', $slugs);
        $this->assertContains('sympathisant', $slugs);
        $this->assertContains('former', $slugs);
        $this->assertSame('fonctionnaire', $set->defaultStatus()?->slug);
    }

    public function test_a_user_belongs_to_a_status_set(): void
    {
        $status = MemberStatus::create(['name' => 'Sympathisant', 'slug' => 'sympathisant']);
        $set = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $set->statuses()->attach($status->id, ['is_default' => false]);

        $user = User::factory()->create(['status_id' => $status->id, 'status_set_id' => $set->id]);

        $this->assertSame($set->id, $user->statusSet->id);
        $this->assertContains('sympathisant', $user->statusSet->statuses->pluck('slug')->all());
    }

    public function test_status_can_belong_to_multiple_sets(): void
    {
        $sympathisant = MemberStatus::create(['name' => 'Sympathisant', 'slug' => 'sympathisant']);
        $setA = StatusSet::create(['name' => 'Externe', 'slug' => 'externe']);
        $setB = StatusSet::create(['name' => 'Fonctionnaire', 'slug' => 'fonctionnaire']);
        $setA->statuses()->attach($sympathisant->id);
        $setB->statuses()->attach($sympathisant->id);

        $this->assertCount(2, $sympathisant->statusSets);
    }
}
