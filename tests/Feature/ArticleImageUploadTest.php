<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class ArticleImageUploadTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $bureau;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Storage::fake('public');
        $this->bureau = $this->createBureauUser();

        $this->article = Article::create([
            'title' => 'Test',
            'slug' => 'test-'.uniqid(),
            'body' => '<p>Body.</p>',
            'article_type' => 'news',
            'is_published' => true,
            'author_id' => $this->bureau->id,
        ]);
    }

    public function test_bureau_can_upload_an_inline_image_and_it_is_re_encoded(): void
    {
        // Wider than the 1600px cap, to prove the server-side downscale runs
        // rather than trusting whatever dimensions the contributor uploaded.
        $file = UploadedFile::fake()->image('photo.jpg', 3000, 200);

        $response = $this->actingAs($this->bureau)
            ->post(route('admin.articles.upload-image', $this->article), ['file' => $file]);

        $response->assertOk()->assertJsonStructure(['location']);

        $location = $response->json('location');
        $relativePath = ltrim(parse_url($location, PHP_URL_PATH), '/');
        // The URL is disk-relative (…/storage/articles/inline/...) — strip the
        // "storage/" prefix the public disk's symlink adds to get the disk path.
        $diskPath = preg_replace('#^storage/#', '', $relativePath);

        Storage::disk('public')->assertExists($diskPath);
        $stored = Storage::disk('public')->get($diskPath);
        $info = getimagesizefromstring($stored);
        $this->assertNotFalse($info, 'Stored file must decode as a valid image.');
        $this->assertLessThanOrEqual(1600, $info[0], 'Width must be capped at 1600px.');
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain');

        $this->actingAs($this->bureau)
            ->post(route('admin.articles.upload-image', $this->article), ['file' => $file])
            ->assertStatus(422);
    }

    public function test_regular_member_cannot_upload_inline_images(): void
    {
        $member = $this->createMemberUser();
        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

        $this->actingAs($member)
            ->post(route('admin.articles.upload-image', $this->article), ['file' => $file])
            ->assertForbidden();
    }
}
