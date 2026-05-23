<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MemberDetail;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class PhotoGalleryTest extends TestCase
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

    public function test_member_can_upload_event_photo(): void
    {
        Storage::fake('public');
        $user = $this->createUser();
        // Grant photo consent
        DB::table('gdpr_consents')->insert([
            'user_id' => $user->id,
            'consent_type' => 'photo_publication',
            'granted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $season = Season::create(['year' => date('Y'), 'name' => date('Y'), 'start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear(), 'is_active' => true]);
        $event = Event::create([
            'title' => 'Dive Trip',
            'event_date' => now()->subDay(),
            'event_type' => 'quarry',
            'season_id' => $season->id,
            'status' => 'open',
        ]);

        $this->actingAs($user)->post("/events/{$event->id}/photos", [
            'photos' => [UploadedFile::fake()->image('dive.jpg', 800, 600)],
        ])->assertRedirect();
    }

    public function test_guest_cannot_upload_photos(): void
    {
        $this->post('/events/1/photos')->assertRedirect('/login');
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
