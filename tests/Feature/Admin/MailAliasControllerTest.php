<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MailAliasDefinition;
use App\Models\User;
use App\Services\MailAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class MailAliasControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $bureauMaster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->bureauMaster = $this->createBureauUser();
    }

    public function test_bureau_master_can_create_an_alias(): void
    {
        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tag' => 'n1-chase',
                'label' => 'N1 students',
                'criteria' => ['pn1'],
                'auth_level' => 'bureau_or_instructor',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mail_alias_definitions', [
            'tag' => 'n1-chase',
            'label' => 'N1 students',
            'auth_level' => 'bureau_or_instructor',
        ]);
    }

    public function test_a_non_bureau_master_cannot_create_an_alias(): void
    {
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);

        $this->actingAs($finance)
            ->post(route('admin.mail-aliases.store'), [
                'tag' => 'n1-chase',
                'label' => 'N1 students',
                'criteria' => ['pn1'],
                'auth_level' => 'bureau',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('mail_alias_definitions', ['tag' => 'n1-chase']);
    }

    public function test_a_non_bureau_master_can_still_view_the_list_and_preview(): void
    {
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);

        $this->actingAs($finance)->get(route('admin.mail-aliases.index'))->assertOk();
        $this->actingAs($finance)->post(route('admin.mail-aliases.preview'), ['criteria' => ['pn1']])->assertOk();
    }

    public function test_bureau_master_can_update_an_alias(): void
    {
        $definition = MailAliasDefinition::create([
            'tag' => 'n1-chase', 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau',
        ]);

        $this->actingAs($this->bureauMaster)
            ->put(route('admin.mail-aliases.update', $definition), [
                'tag' => 'n1-chase',
                'label' => 'N1 students (renamed)',
                'criteria' => ['pn1', 'pn2'],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $this->assertSame(['pn1', 'pn2'], $definition->fresh()->criteria);
        $this->assertSame('N1 students (renamed)', $definition->fresh()->label);
    }

    public function test_bureau_master_can_delete_an_alias(): void
    {
        $definition = MailAliasDefinition::create([
            'tag' => 'n1-chase', 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau',
        ]);

        $this->actingAs($this->bureauMaster)
            ->delete(route('admin.mail-aliases.destroy', $definition))
            ->assertRedirect();

        $this->assertDatabaseMissing('mail_alias_definitions', ['id' => $definition->id]);
    }

    public function test_preview_returns_the_matching_members_for_a_draft_selection_without_persisting_anything(): void
    {
        $n1 = $this->createMemberUser();
        $n1->detail->update(['training_enrollments' => ['N1']]);

        $response = $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.preview'), ['criteria' => ['pn1']])
            ->assertOk();

        $response->assertJsonFragment(['email' => $n1->primary_email]);
        $this->assertSame(0, MailAliasDefinition::count());
    }

    public function test_resolving_an_inbound_alias_matching_a_definitions_tag_routes_to_its_members(): void
    {
        $n1 = $this->createMemberUser();
        $n1->detail->update(['training_enrollments' => ['N1']]);
        MailAliasDefinition::create([
            'tag' => 'n1-chase', 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau_or_instructor',
        ]);

        $resolved = MailAliasService::resolve('n1-chase@club.example');

        $this->assertNotNull($resolved);
        $this->assertContains($n1->primary_email, $resolved['emails']);
        $this->assertSame('bureau_or_instructor', $resolved['auth_level']);
    }

    public function test_a_disabled_definition_is_not_resolved(): void
    {
        MailAliasDefinition::create([
            'tag' => 'n1-chase', 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau', 'enabled' => false,
        ]);

        $this->assertNull(MailAliasService::resolve('n1-chase@club.example'));
    }
}
