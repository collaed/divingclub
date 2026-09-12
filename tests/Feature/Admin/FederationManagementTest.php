<?php

namespace Tests\Feature\Admin;

use App\Models\CertificationLevel;
use App\Models\Federation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class FederationManagementTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_index_and_show_render_for_bureau(): void
    {
        $fed = Federation::create(['acronym' => 'CMAS', 'full_name' => 'World body', 'visibility' => 'active']);
        CertificationLevel::create(['federation_id' => $fed->id, 'code' => '1S', 'name' => 'One star', 'category' => 'diver', 'rank' => 10]);

        $admin = $this->createBureauUser();

        $this->actingAs($admin)->get(route('admin.federations.index'))
            ->assertOk()->assertSee('CMAS')->assertSee('1 level');

        $this->actingAs($admin)->get(route('admin.federations.show', $fed))
            ->assertOk()->assertSee('One star')->assertSee('Equivalence group');
    }

    public function test_plain_member_is_forbidden(): void
    {
        $fed = Federation::create(['acronym' => 'CMAS', 'full_name' => 'World body', 'visibility' => 'active']);

        $this->actingAs($this->createMemberUser())
            ->get(route('admin.federations.index'))
            ->assertForbidden();
    }

    public function test_bureau_can_add_edit_and_delete_a_certification_level(): void
    {
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'French', 'visibility' => 'active']);
        $admin = $this->createBureauUser();

        $this->actingAs($admin)->post(route('admin.federations.levels.store', $fed), [
            'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20, 'equivalence_group' => 'cmas_1s',
        ])->assertRedirect()->assertSessionHas('success');

        $level = CertificationLevel::where('code', 'N1')->firstOrFail();
        $this->assertSame($fed->id, $level->federation_id);

        $this->actingAs($admin)->put(route('admin.federations.levels.bulk-update', $fed), [
            'lvl' => [$level->id => ['code' => 'N1', 'name' => 'Niveau 1 renamed', 'category' => 'diver', 'rank' => 25, 'equivalence_group' => '']],
        ])->assertRedirect();

        $this->assertSame('Niveau 1 renamed', $level->fresh()->name);
        $this->assertSame(25, $level->fresh()->rank);

        $this->actingAs($admin)->delete(route('admin.federations.levels.destroy', [$fed, $level]))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNull(CertificationLevel::find($level->id));
    }

    public function test_duplicate_level_code_within_a_federation_is_rejected(): void
    {
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'French', 'visibility' => 'active']);
        CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N1', 'name' => 'Niveau 1', 'category' => 'diver', 'rank' => 20]);

        $this->actingAs($this->createBureauUser())->post(route('admin.federations.levels.store', $fed), [
            'code' => 'N1', 'name' => 'Dup', 'category' => 'diver', 'rank' => 30,
        ])->assertSessionHasErrors('level');

        $this->assertSame(1, $fed->certificationLevels()->count());
    }

    public function test_level_held_by_a_member_cannot_be_deleted(): void
    {
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'French', 'visibility' => 'active']);
        $level = CertificationLevel::create(['federation_id' => $fed->id, 'code' => 'N2', 'name' => 'Niveau 2', 'category' => 'diver', 'rank' => 40]);
        $holder = User::create([
            'username' => 'holder'.uniqid(), 'primary_email' => 'h'.uniqid().'@t.com',
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $level->users()->attach($holder->id, ['obtained_date' => now()]);

        $this->actingAs($this->createBureauUser())
            ->delete(route('admin.federations.levels.destroy', [$fed, $level]))
            ->assertSessionHasErrors('level');

        $this->assertNotNull(CertificationLevel::find($level->id));
    }

    public function test_deleting_a_federation_with_member_licences_is_blocked(): void
    {
        $fed = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'French', 'visibility' => 'active']);
        $fed->licences()->create([
            'user_id' => $this->createMemberUser()->id,
            'licence_number' => 'X1',
            'season' => (int) now()->year,
        ]);

        $this->actingAs($this->createBureauUser())
            ->delete(route('admin.federations.destroy', $fed))
            ->assertSessionHasErrors('fed');

        $this->assertNotNull(Federation::find($fed->id));
    }
}
