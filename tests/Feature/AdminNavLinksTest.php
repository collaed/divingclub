<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * The admin dropdown nav (components/layout.blade.php) and the "home4" tile
 * dashboard are the two navigation surfaces bureau actually reaches in this
 * app — components/admin-sidebar.blade.php exists but is not included
 * anywhere. A route added to only one of the two live surfaces (as
 * happened with Mail Aliases / Mailing List Contacts) is unreachable from
 * the other, so this pins both staying in sync.
 */
class AdminNavLinksTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_the_admin_dropdown_nav_links_to_mail_aliases_and_mailing_list_contacts(): void
    {
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->get(route('admin.dashboard.index'));

        $response->assertOk();
        $response->assertSee(route('admin.mail-aliases.index'), false);
        $response->assertSee(route('admin.mail-list-contacts.index'), false);
    }

    public function test_the_home4_tile_dashboard_links_to_mail_aliases_and_mailing_list_contacts(): void
    {
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->get(route('home4'));

        $response->assertOk();
        $response->assertSee(route('admin.mail-aliases.index'), false);
        $response->assertSee(route('admin.mail-list-contacts.index'), false);
    }
}
