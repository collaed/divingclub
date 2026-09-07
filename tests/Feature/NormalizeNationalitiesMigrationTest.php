<?php

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class NormalizeNationalitiesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_07_150000_normalize_member_detail_nationalities.php';

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
    }

    public function test_legacy_spellings_are_folded_onto_canonical_names(): void
    {
        $czech = $this->memberWithNationality('Czech Republic');
        $uk = $this->memberWithNationality('UK');
        $usa = $this->memberWithNationality('USA');
        $padded = $this->memberWithNationality('France ');
        $clean = $this->memberWithNationality('Belgium');

        $this->runMigration();

        $this->assertSame('Czechia', $czech->fresh()->nationality);
        $this->assertSame('United Kingdom', $uk->fresh()->nationality);
        $this->assertSame('United States', $usa->fresh()->nationality);
        $this->assertSame('France', $padded->fresh()->nationality);
        $this->assertSame('Belgium', $clean->fresh()->nationality);
    }

    public function test_migration_is_idempotent(): void
    {
        $member = $this->memberWithNationality('Czech Republic');

        $this->runMigration();
        $this->runMigration();

        $this->assertSame('Czechia', $member->fresh()->nationality);
        $this->assertSame(0, DB::table('member_details')->where('nationality', 'Czech Republic')->count());
    }

    public function test_every_alias_target_is_a_canonical_country(): void
    {
        $migration = require database_path(self::MIGRATION);
        $method = new \ReflectionMethod($migration, 'aliasMap');
        $method->setAccessible(true);

        /** @var array<string, string> $map */
        $map = $method->invoke($migration);
        $canonical = config('countries.all');

        $this->assertNotEmpty($map);
        foreach ($map as $legacy => $target) {
            $this->assertContains($target, $canonical, "Alias target [{$target}] for [{$legacy}] is not in config('countries.all')");
        }
    }

    private function runMigration(): void
    {
        (require database_path(self::MIGRATION))->up();
    }

    private function memberWithNationality(string $nationality): MemberDetail
    {
        $user = User::factory()->create();

        return MemberDetail::factory()->create([
            'user_id' => $user->id,
            'nationality' => $nationality,
        ]);
    }
}
