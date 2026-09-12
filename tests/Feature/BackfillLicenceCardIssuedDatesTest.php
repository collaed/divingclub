<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Federation;
use App\Models\MemberLicence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class BackfillLicenceCardIssuedDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function flassaLicence(int $userId): MemberLicence
    {
        $fed = Federation::create(['acronym' => 'FLASSA', 'full_name' => 'FLASSA', 'visibility' => 'active']);

        return MemberLicence::create(['user_id' => $userId, 'federation_id' => $fed->id, 'licence_number' => 'LS-0001']);
    }

    public function test_a_licence_with_no_matching_licence_card_document_is_left_alone(): void
    {
        $user = User::factory()->create();
        $licence = $this->flassaLicence($user->id);

        Artisan::call('licences:backfill-issued-dates');

        $this->assertNull($licence->fresh()->card_issued_at);
    }

    public function test_a_licence_card_document_whose_file_is_missing_on_disk_is_skipped(): void
    {
        $user = User::factory()->create();
        $licence = $this->flassaLicence($user->id);
        Document::create([
            'user_id' => $user->id,
            'category' => 'licence_card',
            'file_path' => 'private/members/'.$user->id.'/gone.pdf',
            'original_filename' => 'gone.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'is_current' => true,
        ]);

        Artisan::call('licences:backfill-issued-dates');

        $this->assertNull($licence->fresh()->card_issued_at);
    }

    public function test_an_already_dated_licence_is_not_touched_again(): void
    {
        $user = User::factory()->create();
        $licence = $this->flassaLicence($user->id);
        $licence->update(['card_issued_at' => '2020-01-01 00:00:00']);
        Document::create([
            'user_id' => $user->id,
            'category' => 'licence_card',
            'file_path' => 'members/'.$user->id.'/card.pdf',
            'original_filename' => 'card.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'is_current' => true,
        ]);
        Storage::disk('local')->put('members/'.$user->id.'/card.pdf', 'not-really-a-pdf');

        Artisan::call('licences:backfill-issued-dates');

        $this->assertSame('2020-01-01 00:00:00', $licence->fresh()->card_issued_at->format('Y-m-d H:i:s'));
    }

    public function test_non_flassa_licences_are_never_touched(): void
    {
        $user = User::factory()->create();
        $ffessm = Federation::create(['acronym' => 'FFESSM', 'full_name' => 'FFESSM', 'visibility' => 'active']);
        $licence = MemberLicence::create(['user_id' => $user->id, 'federation_id' => $ffessm->id, 'licence_number' => 'A-1']);
        Document::create([
            'user_id' => $user->id,
            'category' => 'licence_card',
            'file_path' => 'members/'.$user->id.'/card.pdf',
            'original_filename' => 'card.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'is_current' => true,
        ]);
        Storage::disk('local')->put('members/'.$user->id.'/card.pdf', 'not-really-a-pdf');

        Artisan::call('licences:backfill-issued-dates');

        $this->assertNull($licence->fresh()->card_issued_at);
    }
}
