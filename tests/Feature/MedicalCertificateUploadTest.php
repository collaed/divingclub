<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Caught live: a member submitted "01/01/2026" as a certificate's exam date
 * (not an OCR guess — the field was genuinely left at whatever it defaulted
 * to), and the review screen gave the bureau no way to catch or fix it
 * before validating. The date input now defaults to today, the day someone
 * is most likely to actually be uploading a cert they just received.
 */
#[Group('p1')]
class MedicalCertificateUploadTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_the_upload_forms_date_field_defaults_to_today(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'Test', 'last_name' => 'User']);
        $user->assignRole('member');

        $html = $this->actingAs($user)->get(route('profile.show', ['tab' => 'medical']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input type="date" name="date_established"[^>]*value="'.preg_quote(now()->toDateString(), '/').'"/',
            $html
        );
    }
}
