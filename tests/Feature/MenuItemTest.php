<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class MenuItemTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_resolved_label_falls_back_to_the_articles_current_title_when_no_override_is_set(): void
    {
        $article = Article::factory()->create(['title' => 'Our Values']);
        $item = MenuItem::create(['type' => 'link', 'link_type' => 'article', 'article_id' => $article->id]);

        $this->assertSame('Our Values', $item->resolvedLabel());

        $article->update(['title' => 'Our New Values']);

        $this->assertSame('Our New Values', $item->fresh()->resolvedLabel());
    }

    public function test_resolved_label_respects_an_explicit_override(): void
    {
        $article = Article::factory()->create(['title' => 'Your First Certification — P1★ / 1 Star Diver']);
        $item = MenuItem::create(['type' => 'link', 'link_type' => 'article', 'article_id' => $article->id, 'label' => 'First Certification']);

        $this->assertSame('First Certification', $item->resolvedLabel());

        $article->update(['title' => 'Something else entirely']);

        $this->assertSame('First Certification', $item->fresh()->resolvedLabel());
    }

    public function test_resolved_label_for_an_article_link_uses_the_translation_for_the_given_locale(): void
    {
        $article = Article::factory()->create(['title' => 'Horaire régulier']);
        ArticleTranslation::create(['article_id' => $article->id, 'locale' => 'en', 'title' => 'Training Schedule', 'body' => 'x']);
        $item = MenuItem::create(['type' => 'link', 'link_type' => 'article', 'article_id' => $article->id]);

        $this->assertSame('Training Schedule', $item->resolvedLabel('en'));
        $this->assertSame('Horaire régulier', $item->resolvedLabel('fr'));
    }

    public function test_resolved_url_per_link_type(): void
    {
        $article = Article::factory()->create(['slug' => 'values']);
        $articleItem = MenuItem::create(['type' => 'link', 'link_type' => 'article', 'article_id' => $article->id]);
        $this->assertSame(route('article.show', 'values'), $articleItem->resolvedUrl());

        $routeItem = MenuItem::create(['type' => 'link', 'link_type' => 'route', 'target' => 'events.index']);
        $this->assertSame(route('events.index'), $routeItem->resolvedUrl());

        $missingRouteItem = MenuItem::create(['type' => 'link', 'link_type' => 'route', 'target' => 'no.such.route']);
        $this->assertSame('#', $missingRouteItem->resolvedUrl());

        $urlItem = MenuItem::create(['type' => 'link', 'link_type' => 'url', 'target' => '/horizon']);
        $this->assertSame('/horizon', $urlItem->resolvedUrl());

        $dropdown = MenuItem::create(['type' => 'dropdown', 'label' => 'About']);
        $this->assertSame('#', $dropdown->resolvedUrl());
    }

    public function test_is_visible_to_respects_auth_required_and_roles(): void
    {
        $guestOnly = MenuItem::create(['type' => 'link', 'label' => 'Login', 'auth_required' => false]);
        $authOnly = MenuItem::create(['type' => 'link', 'label' => 'My Profile', 'auth_required' => true]);
        $everyone = MenuItem::create(['type' => 'link', 'label' => 'Home', 'auth_required' => null]);
        $roleGated = MenuItem::create(['type' => 'link', 'label' => 'Instructor Availability', 'roles' => ['instructor', 'bureau_master']]);

        $member = User::factory()->create(['email_verified_at' => now()]);
        $member->assignRole('member');
        $instructor = User::factory()->create(['email_verified_at' => now()]);
        $instructor->assignRole('instructor');

        $this->assertTrue($guestOnly->isVisibleTo(null));
        $this->assertFalse($guestOnly->isVisibleTo($member));

        $this->assertFalse($authOnly->isVisibleTo(null));
        $this->assertTrue($authOnly->isVisibleTo($member));

        $this->assertTrue($everyone->isVisibleTo(null));
        $this->assertTrue($everyone->isVisibleTo($member));

        $this->assertFalse($roleGated->isVisibleTo(null));
        $this->assertFalse($roleGated->isVisibleTo($member));
        $this->assertTrue($roleGated->isVisibleTo($instructor));
    }
}
