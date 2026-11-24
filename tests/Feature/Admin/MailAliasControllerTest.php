<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MailAliasDefinition;
use App\Models\MailListContact;
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
                'tags' => 'n1-chase',
                'label' => 'N1 students',
                'criteria' => ['pn1'],
                'auth_level' => 'bureau_or_instructor',
            ])
            ->assertRedirect();

        $definition = MailAliasDefinition::where('label', 'N1 students')->first();
        $this->assertNotNull($definition);
        $this->assertSame(['n1-chase'], $definition->tags);
        $this->assertSame('bureau_or_instructor', $definition->auth_level);
    }

    public function test_a_dotted_tag_keeps_its_dot(): void
    {
        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'members.b',
                'label' => 'Bureau (custom)',
                'criteria' => ['bureau'],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $definition = MailAliasDefinition::where('label', 'Bureau (custom)')->first();
        $this->assertSame(['members.b'], $definition->tags);
    }

    public function test_a_single_definition_can_carry_several_synonym_tags(): void
    {
        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'bureau2, board2',
                'label' => 'Bureau (dynamic)',
                'criteria' => ['bureau'],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $definition = MailAliasDefinition::where('label', 'Bureau (dynamic)')->first();
        $this->assertSame(['bureau2', 'board2'], $definition->tags);

        $byFirstTag = MailAliasService::resolve('bureau2@club.example');
        $bySecondTag = MailAliasService::resolve('board2@club.example');
        $this->assertSame($byFirstTag, $bySecondTag);
    }

    public function test_a_tag_already_used_by_another_definition_is_rejected(): void
    {
        MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau',
        ]);

        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'n1-chase',
                'label' => 'Duplicate',
                'criteria' => ['pn2'],
                'auth_level' => 'bureau',
            ])
            ->assertSessionHasErrors('tags');

        $this->assertSame(1, MailAliasDefinition::count());
    }

    public function test_an_alias_can_be_built_from_named_members_only_with_no_criteria(): void
    {
        $named = $this->createMemberUser();

        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'steering-group',
                'label' => 'Steering group',
                'include_user_ids' => [$named->id],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $definition = MailAliasDefinition::where('label', 'Steering group')->first();
        $this->assertSame([], $definition->criteria);
        $this->assertSame([$named->id], $definition->include_user_ids);
        $this->assertContains($named->primary_email, MailAliasService::resolve('steering-group@club.example')['emails']);
    }

    public function test_an_alias_can_include_a_mailing_list_contact(): void
    {
        $contact = MailListContact::create(['email' => 'prospect@example.com']);

        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'newsletter',
                'label' => 'Newsletter',
                'include_contact_ids' => [$contact->id],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $definition = MailAliasDefinition::where('label', 'Newsletter')->first();
        $this->assertSame([$contact->id], $definition->include_contact_ids);

        $resolved = MailAliasService::resolve('newsletter@club.example');
        $this->assertContains('prospect@example.com', $resolved['emails']);
        $this->assertArrayHasKey('prospect@example.com', $resolved['contact_unsubscribe_tokens']);
    }

    public function test_an_alias_can_include_all_subscribed_contacts_without_naming_them(): void
    {
        $subscribed = MailListContact::create(['email' => 'prospect1@example.com']);
        $alsoSubscribed = MailListContact::create(['email' => 'prospect2@example.com']);
        $unsubscribed = MailListContact::create(['email' => 'gone@example.com', 'subscribed' => false]);

        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'prospects',
                'label' => 'Prospects',
                'include_all_contacts' => '1',
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $definition = MailAliasDefinition::where('label', 'Prospects')->first();
        $this->assertTrue($definition->include_all_contacts);
        $this->assertSame([], $definition->include_contact_ids);

        $resolved = MailAliasService::resolve('prospects@club.example');
        $this->assertContains($subscribed->email, $resolved['emails']);
        $this->assertContains($alsoSubscribed->email, $resolved['emails']);
        $this->assertNotContains($unsubscribed->email, $resolved['emails']);

        // A contact added after the alias was saved is picked up too — it's
        // not a snapshot of who was subscribed at save time.
        $later = MailListContact::create(['email' => 'prospect3@example.com']);
        $resolved = MailAliasService::resolve('prospects@club.example');
        $this->assertContains($later->email, $resolved['emails']);
    }

    public function test_preview_includes_all_subscribed_contacts_when_that_flag_is_set(): void
    {
        $subscribed = MailListContact::create(['email' => 'prospect@example.com', 'name' => 'Prospect']);
        MailListContact::create(['email' => 'gone@example.com', 'subscribed' => false]);

        $response = $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.preview'), ['include_all_contacts' => '1'])
            ->assertOk();

        $response->assertJsonFragment(['name' => 'Prospect', 'email' => $subscribed->email]);
        $response->assertJsonMissing(['email' => 'gone@example.com']);
    }

    public function test_an_unsubscribed_contact_is_excluded_from_resolution(): void
    {
        $contact = MailListContact::create(['email' => 'prospect@example.com', 'subscribed' => false]);
        MailAliasDefinition::create([
            'tags' => ['newsletter'], 'label' => 'Newsletter', 'criteria' => [], 'include_contact_ids' => [$contact->id], 'auth_level' => 'bureau',
        ]);

        $resolved = MailAliasService::resolve('newsletter@club.example');

        $this->assertNotContains('prospect@example.com', $resolved['emails']);
    }

    public function test_preview_includes_a_draft_contact_selection(): void
    {
        $contact = MailListContact::create(['email' => 'prospect@example.com', 'name' => 'Prospect']);

        $response = $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.preview'), ['include_contact_ids' => [$contact->id]])
            ->assertOk();

        $response->assertJsonFragment(['name' => 'Prospect', 'email' => 'prospect@example.com']);
    }

    public function test_creating_an_alias_with_no_criteria_and_no_named_members_is_rejected(): void
    {
        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'empty-group',
                'label' => 'Empty',
                'auth_level' => 'bureau',
            ])
            ->assertSessionHasErrors('criteria');

        $this->assertSame(0, MailAliasDefinition::count());
    }

    public function test_updating_an_alias_to_remove_all_named_members_clears_the_stored_list(): void
    {
        $named = $this->createMemberUser();
        $definition = MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'],
            'include_user_ids' => [$named->id], 'auth_level' => 'bureau',
        ]);

        $this->actingAs($this->bureauMaster)
            ->put(route('admin.mail-aliases.update', $definition), [
                'tags' => 'n1-chase',
                'label' => 'N1 students',
                'criteria' => ['pn1'],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $this->assertSame([], $definition->fresh()->include_user_ids);
    }

    public function test_preview_honors_a_draft_include_and_exclude_selection(): void
    {
        $included = $this->createMemberUser();
        $excluded = $this->createMemberUser();
        $excluded->detail->update(['bureau_member' => true]);

        $response = $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-aliases.preview'), [
                'criteria' => ['bureau'],
                'include_user_ids' => [$included->id],
                'exclude_user_ids' => [$excluded->id],
            ])
            ->assertOk();

        $response->assertJsonFragment(['email' => $included->primary_email]);
        $response->assertJsonMissing(['email' => $excluded->primary_email]);
    }

    public function test_a_non_bureau_master_cannot_create_an_alias(): void
    {
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);

        $this->actingAs($finance)
            ->post(route('admin.mail-aliases.store'), [
                'tags' => 'n1-chase',
                'label' => 'N1 students',
                'criteria' => ['pn1'],
                'auth_level' => 'bureau',
            ])
            ->assertForbidden();

        $this->assertSame(0, MailAliasDefinition::count());
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
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau',
        ]);

        $this->actingAs($this->bureauMaster)
            ->put(route('admin.mail-aliases.update', $definition), [
                'tags' => 'n1-chase, n1',
                'label' => 'N1 students (renamed)',
                'criteria' => ['pn1', 'pn2'],
                'auth_level' => 'bureau',
            ])
            ->assertRedirect();

        $this->assertSame(['pn1', 'pn2'], $definition->fresh()->criteria);
        $this->assertSame(['n1-chase', 'n1'], $definition->fresh()->tags);
        $this->assertSame('N1 students (renamed)', $definition->fresh()->label);
    }

    public function test_bureau_master_can_delete_an_alias(): void
    {
        $definition = MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau',
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
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau_or_instructor',
        ]);

        $resolved = MailAliasService::resolve('n1-chase@club.example');

        $this->assertNotNull($resolved);
        $this->assertContains($n1->primary_email, $resolved['emails']);
        $this->assertSame('bureau_or_instructor', $resolved['auth_level']);
    }

    public function test_a_disabled_definition_is_not_resolved(): void
    {
        MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'bureau', 'enabled' => false,
        ]);

        $this->assertNull(MailAliasService::resolve('n1-chase@club.example'));
    }

    public function test_a_member_of_an_open_alias_group_can_send_without_being_bureau(): void
    {
        $n1 = $this->createMemberUser();
        $n1->detail->update(['training_enrollments' => ['N1']]);
        MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'group_or_bureau',
        ]);

        $this->assertTrue(MailAliasService::isAuthorized($n1->primary_email, 'n1-chase@club.example'));
    }

    public function test_a_non_group_member_is_rejected_under_group_or_bureau(): void
    {
        $outsider = $this->createMemberUser();
        MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'group_or_bureau',
        ]);

        $this->assertFalse(MailAliasService::isAuthorized($outsider->primary_email, 'n1-chase@club.example'));
    }

    public function test_any_member_auth_level_allows_any_registered_member(): void
    {
        $member = $this->createMemberUser();
        MailAliasDefinition::create([
            'tags' => ['suggestions'], 'label' => 'Suggestions', 'criteria' => ['current_member'], 'auth_level' => 'any_member',
        ]);

        $this->assertTrue(MailAliasService::isAuthorized($member->primary_email, 'suggestions@club.example'));
        $this->assertFalse(MailAliasService::isAuthorized('stranger@external.test', 'suggestions@club.example'));
    }

    public function test_open_auth_level_allows_any_address(): void
    {
        MailAliasDefinition::create([
            'tags' => ['contact'], 'label' => 'Contact', 'criteria' => ['bureau'], 'auth_level' => 'open',
        ]);

        $this->assertTrue(MailAliasService::isAuthorized('stranger@external.test', 'contact@club.example'));
    }
}
