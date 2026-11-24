<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * Editing a communication's body (EmailController::updateLog()) must apply
 * to every EmailLog row in the same thread — one inbound message is logged
 * as one row per recipient (see EmailLog::threadKey()), and the event page
 * displays them as a single communication, so an edit to it must not leave
 * some recipients' copies showing the old text.
 */
class EmailLogUpdateTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function threadRows(): array
    {
        $shared = ['subject' => 'Batch message', 'from_email' => 'sender@example.com', 'created_at' => '2026-01-01 10:00:00'];

        return [
            EmailLog::create([...$shared, 'to_email' => 'a@example.com', 'body' => 'original', 'status' => 'forwarded', 'direction' => 'inbound']),
            EmailLog::create([...$shared, 'to_email' => 'b@example.com', 'body' => 'original', 'status' => 'forwarded', 'direction' => 'inbound']),
        ];
    }

    public function test_bureau_can_edit_a_communication_and_every_row_in_its_thread_updates(): void
    {
        [$first, $second] = $this->threadRows();
        $bureau = $this->createBureauUser();

        $response = $this->actingAs($bureau)->put(route('admin.email.update', $first), ['body' => 'edited text']);

        $response->assertRedirect();
        $this->assertSame('edited text', $first->fresh()->body);
        $this->assertSame('edited text', $second->fresh()->body, 'Editing one row should update every row in the same thread.');
    }

    public function test_a_plain_member_cannot_edit_a_communication(): void
    {
        [$first] = $this->threadRows();
        $member = $this->createMemberUser();

        $this->actingAs($member)->put(route('admin.email.update', $first), ['body' => 'hacked'])
            ->assertForbidden();

        $this->assertSame('original', $first->fresh()->body);
    }

    public function test_editing_a_communication_in_another_thread_is_unaffected(): void
    {
        [$first] = $this->threadRows();
        $unrelated = EmailLog::create([
            'subject' => 'Different message', 'from_email' => 'other@example.com',
            'to_email' => 'c@example.com', 'body' => 'untouched', 'status' => 'forwarded', 'direction' => 'inbound',
        ]);
        $bureau = $this->createBureauUser();

        $this->actingAs($bureau)->put(route('admin.email.update', $first), ['body' => 'edited text']);

        $this->assertSame('untouched', $unrelated->fresh()->body);
    }
}
