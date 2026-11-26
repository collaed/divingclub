<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Article cards on the homepage must honor the viewer's locale, same as the
 * article show page already does (see Article::translated()) — previously
 * the cards always rendered the raw (French) source columns regardless of
 * locale, even when an English translation already existed.
 */
#[Group('p1')]
class ArticleCardLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');
    }

    private function createUser(string $locale): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id') ?? 2;

        $user = User::create([
            'username' => 'member'.uniqid(),
            'primary_email' => 'member'.uniqid().'@test.com',
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('member');
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Member', 'last_name' => 'Test', 'preferred_language' => $locale]);

        return $user;
    }

    public function test_the_homepage_article_card_shows_the_viewers_locale_not_the_source_language(): void
    {
        $author = $this->createUser('fr');
        $article = Article::create([
            'title' => 'Titre Francais Unique',
            'slug' => 'titre-francais-unique',
            'body' => '<p>Contenu francais.</p>',
            'article_type' => 'news',
            'is_published' => true,
            'is_public' => true,
            'author_id' => $author->id,
        ]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'locale' => 'en',
            'title' => 'Unique English Title',
            'body' => '<p>English content.</p>',
        ]);

        $enUser = $this->createUser('en');

        $response = $this->actingAs($enUser)
            ->withCookie('cep_seen_landing', '1')
            ->get('/');

        $response->assertOk()
            ->assertSee('Unique English Title')
            ->assertDontSee('Titre Francais Unique');
    }

    public function test_the_homepage_article_card_falls_back_to_the_source_when_no_translation_exists(): void
    {
        $author = $this->createUser('fr');
        Article::create([
            'title' => 'Seul Titre Francais',
            'slug' => 'seul-titre-francais',
            'body' => '<p>Contenu.</p>',
            'article_type' => 'news',
            'is_published' => true,
            'is_public' => true,
            'author_id' => $author->id,
        ]);

        $enUser = $this->createUser('en');

        $this->actingAs($enUser)
            ->withCookie('cep_seen_landing', '1')
            ->get('/')
            ->assertOk()
            ->assertSee('Seul Titre Francais');
    }
}
