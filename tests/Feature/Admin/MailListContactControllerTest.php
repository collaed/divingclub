<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\MailListContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class MailListContactControllerTest extends TestCase
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

    public function test_index_links_back_to_mail_aliases_management(): void
    {
        $response = $this->actingAs($this->bureauMaster)->get(route('admin.mail-list-contacts.index'));

        $response->assertOk();
        $response->assertSee(route('admin.mail-aliases.index'), false);
    }

    public function test_bureau_master_can_bulk_add_contacts_parsing_both_line_formats(): void
    {
        $this->actingAs($this->bureauMaster)
            ->post(route('admin.mail-list-contacts.store'), [
                'entries' => "jane@example.com\nJohn Doe <john@example.com>",
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mail_list_contacts', ['email' => 'jane@example.com', 'name' => null]);
        $this->assertDatabaseHas('mail_list_contacts', ['email' => 'john@example.com', 'name' => 'John Doe']);
    }

    public function test_bulk_add_skips_duplicates_and_invalid_lines(): void
    {
        MailListContact::create(['email' => 'jane@example.com']);

        $response = $this->actingAs($this->bureauMaster)
            ->postJson(route('admin.mail-list-contacts.store'), [
                'entries' => "jane@example.com\nnot-an-email",
            ])
            ->assertOk();

        $response->assertJson(['added' => 0, 'skipped' => 2]);
        $this->assertSame(1, MailListContact::count());
    }

    public function test_toggle_flips_subscribed(): void
    {
        $contact = MailListContact::create(['email' => 'jane@example.com']);

        $this->actingAs($this->bureauMaster)
            ->patch(route('admin.mail-list-contacts.toggle', $contact))
            ->assertRedirect();

        $this->assertFalse($contact->fresh()->subscribed);
    }

    public function test_non_bureau_master_gets_403_on_mutations_but_200_on_index(): void
    {
        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);
        $contact = MailListContact::create(['email' => 'jane@example.com']);

        $this->actingAs($finance)->get(route('admin.mail-list-contacts.index'))->assertOk();
        $this->actingAs($finance)->post(route('admin.mail-list-contacts.store'), ['entries' => 'x@example.com'])->assertForbidden();
        $this->actingAs($finance)->patch(route('admin.mail-list-contacts.toggle', $contact))->assertForbidden();
        $this->actingAs($finance)->delete(route('admin.mail-list-contacts.destroy', $contact))->assertForbidden();
    }

    public function test_bureau_master_can_remove_a_contact(): void
    {
        $contact = MailListContact::create(['email' => 'jane@example.com']);

        $this->actingAs($this->bureauMaster)
            ->delete(route('admin.mail-list-contacts.destroy', $contact))
            ->assertRedirect();

        $this->assertDatabaseMissing('mail_list_contacts', ['id' => $contact->id]);
    }
}
