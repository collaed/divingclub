<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendTrackedDocumentEmail;
use App\Models\DocumentDispatch;
use App\Models\DocumentDispatchRecipient;
use App\Models\LibraryFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

#[Group('p1')]
class TrackedDocumentDispatchTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Http::fake(['ipwho.is/*' => Http::response(['success' => true, 'country_code' => 'BE', 'country' => 'Belgium'])]);
    }

    private function pdf(): LibraryFile
    {
        Storage::fake('local');
        Storage::disk('local')->put('lib/agm.pdf', '%PDF-1.4 minimal');

        return LibraryFile::create([
            'filename' => 'agm.pdf', 'original_name' => 'AGM 2026.pdf', 'path' => 'lib/agm.pdf',
            'mime_type' => 'application/pdf', 'size' => 20, 'visibility' => 'members', 'uploaded_by' => 1,
        ]);
    }

    private function member(string $email): User
    {
        $u = User::create([
            'username' => 'm'.uniqid(), 'primary_email' => $email,
            'password' => 'Password1', 'role_id' => 2, 'status_id' => 1, 'email_verified_at' => now(),
        ]);
        $u->assignRole('member');

        return $u;
    }

    public function test_only_bureau_master_may_reach_it(): void
    {
        $this->actingAs($this->member('m@x.com'))->get(route('admin.document-dispatch.index'))->assertForbidden();

        $finance = $this->createBureauUser();
        $finance->syncRoles(['bureau_finance']);
        $this->actingAs($finance)->get(route('admin.document-dispatch.index'))->assertForbidden();

        $this->actingAs($this->createBureauUser())->get(route('admin.document-dispatch.index'))->assertOk();
    }

    public function test_send_creates_a_dispatch_with_a_unique_token_per_recipient(): void
    {
        Bus::fake();
        $file = $this->pdf();
        $a = $this->member('a@x.com');
        $b = $this->member('b@x.com');

        $this->actingAs($this->createBureauUser())->post(route('admin.document-dispatch.store'), [
            'library_file_id' => $file->id,
            'subject' => 'Please read the AGM convocation',
            'message' => 'See attached.',
            'recipients' => [$a->id, $b->id],
        ])->assertRedirect();

        $dispatch = DocumentDispatch::firstOrFail();
        $this->assertSame(2, $dispatch->recipients()->count());
        $tokens = $dispatch->recipients()->pluck('token');
        $this->assertSame(2, $tokens->unique()->count());
        $this->assertSame(40, strlen($tokens->first()));
        Bus::assertDispatchedTimes(SendTrackedDocumentEmail::class, 2);
    }

    public function test_opening_the_link_records_the_open_and_serves_the_pdf(): void
    {
        $file = $this->pdf();
        $dispatch = DocumentDispatch::create(['library_file_id' => $file->id, 'subject' => 'x', 'created_by' => null]);
        $r = DocumentDispatchRecipient::create([
            'dispatch_id' => $dispatch->id, 'user_id' => $this->member('c@x.com')->id,
            'email' => 'c@x.com', 'token' => str_repeat('a', 40),
        ]);

        $this->get('/d/'.$r->token)->assertOk()->assertHeader('content-disposition');
        $this->get('/d/'.$r->token)->assertOk();

        $r->refresh();
        $this->assertSame(2, $r->opens_count);
        $this->assertNotNull($r->first_opened_at);
        $this->assertSame(2, $r->opens()->count());
        $this->assertNotNull($r->opens()->first()->ip_address);
    }

    public function test_a_bad_token_is_404(): void
    {
        $this->get('/d/nope')->assertNotFound();
    }
}
