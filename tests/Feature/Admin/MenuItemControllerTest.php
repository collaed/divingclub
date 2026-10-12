<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class MenuItemControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Permission::findOrCreate('manage articles', 'web');
        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('bureau_master');
        $this->admin->givePermissionTo('manage articles');
    }

    public function test_guest_cannot_reach_the_navigation_screen(): void
    {
        $this->get(route('admin.menu-items.index'))->assertRedirect(route('login'));
    }

    public function test_bureau_can_list_menu_items(): void
    {
        MenuItem::create(['type' => 'dropdown', 'label' => 'About']);

        $this->actingAs($this->admin)
            ->get(route('admin.menu-items.index'))
            ->assertOk();
    }

    public function test_adding_a_dropdown_returns_rendered_html_without_a_redirect(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.menu-items.store'), ['type' => 'dropdown', 'label' => 'Resources']);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('menu_items', ['type' => 'dropdown', 'label' => 'Resources']);
    }

    public function test_adding_a_link_to_an_article_does_not_require_a_label(): void
    {
        $dropdown = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);
        $article = Article::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.menu-items.store'), [
                'parent_id' => $dropdown->id,
                'type' => 'link',
                'link_type' => 'article',
                'article_id' => $article->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('menu_items', ['parent_id' => $dropdown->id, 'article_id' => $article->id, 'label' => null]);
    }

    public function test_adding_a_route_link_without_a_label_fails_validation(): void
    {
        $dropdown = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.menu-items.store'), [
                'parent_id' => $dropdown->id,
                'type' => 'link',
                'link_type' => 'route',
                'target' => 'events.index',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label']);
    }

    public function test_adding_a_link_with_a_route_that_does_not_exist_fails_validation(): void
    {
        $dropdown = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.menu-items.store'), [
                'parent_id' => $dropdown->id,
                'type' => 'link',
                'link_type' => 'route',
                'label' => 'Nowhere',
                'target' => 'no.such.route',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target']);
    }

    public function test_cannot_nest_a_menu_item_under_a_non_dropdown(): void
    {
        $dropdown = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);
        $leaf = MenuItem::create(['parent_id' => $dropdown->id, 'type' => 'link', 'link_type' => 'route', 'label' => 'Events', 'target' => 'events.index']);

        $this->actingAs($this->admin)
            ->postJson(route('admin.menu-items.store'), [
                'parent_id' => $leaf->id,
                'type' => 'link',
                'link_type' => 'route',
                'label' => 'Too deep',
                'target' => 'events.index',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_updating_a_label_is_a_partial_patch_that_does_not_require_other_fields(): void
    {
        $article = Article::factory()->create();
        $item = MenuItem::create(['type' => 'link', 'link_type' => 'article', 'article_id' => $article->id]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.menu-items.update', $item), ['label' => 'Custom label'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('Custom label', $item->fresh()->label);
    }

    public function test_deleting_a_menu_item(): void
    {
        $item = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.menu-items.destroy', $item))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_deleting_a_dropdown_cascades_to_its_children(): void
    {
        $dropdown = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);
        $child = MenuItem::create(['parent_id' => $dropdown->id, 'type' => 'link', 'link_type' => 'route', 'label' => 'Events', 'target' => 'events.index']);

        $this->actingAs($this->admin)->deleteJson(route('admin.menu-items.destroy', $dropdown))->assertOk();

        $this->assertDatabaseMissing('menu_items', ['id' => $child->id]);
    }

    public function test_bureau_user_without_manage_articles_permission_is_forbidden(): void
    {
        // seedRoles() replaces bureau_master's permission set with a fixed
        // list that excludes 'manage articles' (the real app grants it via
        // migrations, which this lighter test helper intentionally doesn't
        // replicate) — giving us a bureau role that clears the route-level
        // gate but should still be denied by the request's own authorize().
        $bureauWithoutPermission = User::factory()->create(['email_verified_at' => now()]);
        $bureauWithoutPermission->assignRole('bureau_master');

        $this->actingAs($bureauWithoutPermission)
            ->postJson(route('admin.menu-items.store'), ['type' => 'dropdown', 'label' => 'Hack'])
            ->assertForbidden();
    }
}
