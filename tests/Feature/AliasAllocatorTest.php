<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MailAlias;
use App\Models\MemberDetail;
use App\Models\User;
use App\Services\AliasAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AliasAllocatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'actif']);
    }

    public function test_suggests_first_name_when_available(): void
    {
        $user = $this->makeUser('Jean', 'Dupont');

        $this->assertSame('jean', AliasAllocator::suggest($user));
    }

    public function test_second_jean_gets_last_initial(): void
    {
        MailAlias::factory()->create(['alias' => 'jean']);
        $user = $this->makeUser('Jean', 'Dupont');

        $this->assertSame('jeand', AliasAllocator::suggest($user));
    }

    public function test_third_jean_dupont_extends_last_name(): void
    {
        MailAlias::factory()->create(['alias' => 'jean']);
        MailAlias::factory()->create(['alias' => 'jeand']);
        $user = $this->makeUser('Jean', 'Dupont');

        $this->assertSame('jeandu', AliasAllocator::suggest($user));
    }

    public function test_numeric_suffix_last_resort_when_full_name_taken(): void
    {
        // Occupy firstname + every prefix of the last name AND the full name.
        foreach (['jean', 'jeand', 'jeandu', 'jeandup', 'jeandupo', 'jeandupon', 'jeandupont'] as $taken) {
            MailAlias::factory()->create(['alias' => $taken]);
        }
        $user = $this->makeUser('Jean', 'Dupont');

        $this->assertSame('jeandupont2', AliasAllocator::suggest($user));
    }

    public function test_accented_and_hyphenated_names_are_normalized(): void
    {
        $user = $this->makeUser('Jean-François', 'Éléonore');

        $this->assertSame('jeanfrancois', AliasAllocator::suggest($user));
    }

    public function test_ignore_alias_id_allows_reusing_own_alias(): void
    {
        $user = $this->makeUser('Jean', 'Dupont');
        $alias = MailAlias::factory()->create(['alias' => 'jean', 'user_id' => $user->id]);

        // Without ignoring, "jean" is taken and we'd get "jeand".
        $this->assertSame('jeand', AliasAllocator::suggest($user));
        // Ignoring the member's own row, "jean" is available again.
        $this->assertSame('jean', AliasAllocator::suggest($user, $alias->id));
    }

    private function makeUser(string $first, string $last): User
    {
        $user = User::create([
            'username' => fake()->unique()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => $first, 'last_name' => $last]);
        $user->load('detail');

        return $user;
    }
}
