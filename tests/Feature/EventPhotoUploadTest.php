<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\GdprConsent;
use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class EventPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        DB::table($roleTable)->insertOrIgnore(['id' => 2, 'name' => 'Member', 'slug' => 'member']);
        DB::table('member_statuses')->insertOrIgnore(['id' => 1, 'name' => 'Active', 'slug' => 'active']);
        SpatieRole::findOrCreate('member', 'web');

        $this->user = $this->createUser();
        $this->event = Event::create([
            'title' => 'Test Dive',
            'event_date' => now()->addDays(7),
            'event_type' => 'quarry',
            'created_by' => $this->user->id,
        ]);

        GdprConsent::create([
            'user_id' => $this->user->id,
            'consent_type' => 'photo_publication',
            'granted' => true,
        ]);
    }

    public function test_upload_single_image(): void
    {
        $response = $this->actingAs($this->user)->post(
            route('events.photo.upload', $this->event),
            [
                'photos' => [UploadedFile::fake()->image('dive.jpg', 800, 600)],
                'gdpr_consent' => '1',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseCount('event_photos', 1);
        $photo = EventPhoto::first();
        $this->assertStringStartsWith('event-photos/'.$this->event->id.'/', $photo->path);
        $this->assertNotNull($photo->mime_type);
    }

    public function test_upload_video_file(): void
    {
        $response = $this->actingAs($this->user)->post(
            route('events.photo.upload', $this->event),
            [
                'photos' => [UploadedFile::fake()->create('dive.mp4', 5000, 'video/mp4')],
                'gdpr_consent' => '1',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseCount('event_photos', 1);
        $photo = EventPhoto::first();
        $this->assertEquals('video/mp4', $photo->mime_type);
        $this->assertTrue($photo->isVideo());
    }

    public function test_upload_zip_extracts_images(): void
    {
        // Create a real zip with two fake images inside
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Add two small JPEG-like files
        $img1 = $this->createMinimalJpeg();
        $img2 = $this->createMinimalJpeg();
        $zip->addFromString('photo1.jpg', $img1);
        $zip->addFromString('photo2.jpg', $img2);
        $zip->addFromString('__MACOSX/.DS_Store', 'junk');
        $zip->close();

        $response = $this->actingAs($this->user)->post(
            route('events.photo.upload', $this->event),
            [
                'photos' => [new UploadedFile($zipPath, 'photos.zip', 'application/zip', null, true)],
                'gdpr_consent' => '1',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseCount('event_photos', 2);
        unlink($zipPath);
    }

    public function test_duplicate_files_are_skipped(): void
    {
        $file = UploadedFile::fake()->image('dive.jpg', 800, 600);

        $this->actingAs($this->user)->post(
            route('events.photo.upload', $this->event),
            ['photos' => [$file], 'gdpr_consent' => '1']
        );

        // Upload same content again
        $file2 = UploadedFile::fake()->image('dive.jpg', 800, 600);
        $response = $this->actingAs($this->user)->post(
            route('events.photo.upload', $this->event),
            ['photos' => [$file2], 'gdpr_consent' => '1']
        );

        $response->assertRedirect();
        // Fake images with same dimensions produce same content
        $this->assertDatabaseCount('event_photos', 1);
    }

    public function test_upload_requires_gdpr_consent(): void
    {
        $response = $this->actingAs($this->user)->post(
            route('events.photo.upload', $this->event),
            [
                'photos' => [UploadedFile::fake()->image('dive.jpg')],
            ]
        );

        $response->assertSessionHasErrors('gdpr_consent');
    }

    public function test_guest_cannot_upload(): void
    {
        $this->post(route('events.photo.upload', $this->event), [
            'photos' => [UploadedFile::fake()->image('dive.jpg')],
            'gdpr_consent' => '1',
        ])->assertRedirect('/login');
    }

    /** Create a minimal valid JPEG binary. */
    private function createMinimalJpeg(): string
    {
        $img = imagecreatetruecolor(10, 10);
        $color = imagecolorallocate($img, rand(0, 255), rand(0, 255), rand(0, 255));
        imagefill($img, 0, 0, $color);
        ob_start();
        imagejpeg($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }

    private function createUser(): User
    {
        $roleTable = \Schema::hasTable('legacy_roles') ? 'legacy_roles' : 'roles';
        $roleId = DB::table($roleTable)->where('slug', 'member')->value('id')
            ?? DB::table($roleTable)->where('name', 'member')->value('id') ?? 2;

        $u = User::create([
            'username' => fake()->userName(),
            'primary_email' => fake()->unique()->safeEmail(),
            'password' => 'Password1',
            'role_id' => $roleId,
            'status_id' => 1,
            'email_verified_at' => now(),
        ]);
        $u->assignRole('member');
        MemberDetail::create(['user_id' => $u->id, 'first_name' => 'Test', 'last_name' => 'User']);

        return $u;
    }
}
