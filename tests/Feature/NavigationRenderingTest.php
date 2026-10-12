<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * The home page ('/') can render a theme-selected alternate landing template
 * with no shared navbar at all, so these tests hit pages that always use
 * <x-layout> (login for guests, profile for authenticated members).
 */
#[Group('p1')]
class NavigationRenderingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_an_article_linked_nav_item_changes_label_when_the_article_title_changes(): void
    {
        $article = Article::factory()->create(['title' => 'Our Values']);
        $about = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);
        MenuItem::create(['parent_id' => $about->id, 'type' => 'link', 'link_type' => 'article', 'article_id' => $article->id]);

        $this->get('/login')->assertSee('Our Values');

        $article->update(['title' => 'Nos Valeurs']);

        $this->get('/login')->assertSee('Nos Valeurs')->assertDontSee('Our Values');
    }

    public function test_a_guest_does_not_see_an_auth_only_dropdown(): void
    {
        // "Calendar" itself is a bad probe string here — it's a substring of
        // the flatpickr "noCalendar" JS config option present on every page.
        $calendar = MenuItem::create(['type' => 'dropdown', 'label' => 'AuthOnlyTestDropdown', 'auth_required' => true]);
        MenuItem::create(['parent_id' => $calendar->id, 'type' => 'link', 'link_type' => 'route', 'label' => 'Events', 'target' => 'events.index']);

        $this->get('/login')->assertDontSee('AuthOnlyTestDropdown');
    }

    public function test_a_logged_in_member_sees_an_auth_only_dropdown(): void
    {
        $calendar = MenuItem::create(['type' => 'dropdown', 'label' => 'Calendar', 'auth_required' => true]);
        MenuItem::create(['parent_id' => $calendar->id, 'type' => 'link', 'link_type' => 'route', 'label' => 'Events', 'target' => 'events.index']);

        $member = $this->createMemberUser();

        $this->actingAs($member)->get(route('profile.show'))->assertSee('Calendar');
    }

    public function test_a_role_gated_item_is_hidden_from_members_without_that_role(): void
    {
        $calendar = MenuItem::create(['type' => 'dropdown', 'label' => 'Calendar', 'auth_required' => true]);
        MenuItem::create(['parent_id' => $calendar->id, 'type' => 'link', 'link_type' => 'route', 'label' => 'Events', 'target' => 'events.index']);
        MenuItem::create(['parent_id' => $calendar->id, 'type' => 'link', 'label' => 'Instructor Availability', 'link_type' => 'route', 'target' => 'availability.index', 'roles' => ['instructor', 'bureau_master']]);

        $member = $this->createMemberUser();

        $this->actingAs($member)->get(route('profile.show'))
            ->assertSee('Events')
            ->assertDontSee('Instructor Availability');
    }

    public function test_a_dropdown_with_no_visible_children_is_not_rendered(): void
    {
        $calendar = MenuItem::create(['type' => 'dropdown', 'label' => 'EmptyTestDropdown', 'auth_required' => true]);
        MenuItem::create(['parent_id' => $calendar->id, 'type' => 'link', 'label' => 'Instructor Availability', 'link_type' => 'route', 'target' => 'availability.index', 'roles' => ['instructor']]);

        $member = $this->createMemberUser();

        $this->actingAs($member)->get(route('profile.show'))->assertDontSee('EmptyTestDropdown');
    }
}
