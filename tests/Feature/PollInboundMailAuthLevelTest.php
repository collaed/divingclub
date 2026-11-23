<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\PollInboundMail;
use App\Models\EmailLog;
use App\Models\MailAliasDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionMethod;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

/**
 * PollInboundMail::processMessage() must honor MailAliasDefinition::auth_level
 * via MailAliasService::authorizedFor() — before this, the live inbound
 * pipeline hardcoded a bureau-or-instructor gate regardless of what an alias
 * definition actually allowed.
 */
class PollInboundMailAuthLevelTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function processMessage(string $from, string $to, string $subject, string $body): void
    {
        $method = new ReflectionMethod(PollInboundMail::class, 'processMessage');
        $method->invoke(new PollInboundMail, $from, $to, $subject, $body);
    }

    public function test_an_open_alias_accepts_mail_from_any_address(): void
    {
        $bureau = $this->createBureauUser();
        $bureau->detail->update(['bureau_member' => true]);
        MailAliasDefinition::create([
            'tags' => ['contact'], 'label' => 'Contact', 'criteria' => ['bureau'], 'auth_level' => 'open',
        ]);

        Mail::shouldReceive('html')->once();
        Mail::shouldReceive('raw')->once();

        $this->processMessage('stranger@external.test', 'contact@example.com', 'Hello', 'Hi there');

        $this->assertNotNull(EmailLog::where('status', 'forwarded')->first());
    }

    public function test_a_group_or_bureau_alias_accepts_mail_from_a_group_member(): void
    {
        $n1 = $this->createMemberUser();
        $n1->detail->update(['training_enrollments' => ['N1']]);
        MailAliasDefinition::create([
            'tags' => ['n1-chase'], 'label' => 'N1 students', 'criteria' => ['pn1'], 'auth_level' => 'group_or_bureau',
        ]);

        Mail::shouldReceive('html')->once();
        Mail::shouldReceive('raw')->once();

        $this->processMessage($n1->primary_email, 'n1-chase@example.com', 'Hello', 'Hi there');

        $this->assertNotNull(EmailLog::where('status', 'forwarded')->first());
    }

    public function test_a_bureau_only_alias_still_rejects_a_plain_member(): void
    {
        $member = $this->createMemberUser();
        MailAliasDefinition::create([
            'tags' => ['board-only'], 'label' => 'Board only', 'criteria' => ['bureau'], 'auth_level' => 'bureau',
        ]);

        Mail::shouldReceive('html')->never();

        $this->processMessage($member->primary_email, 'board-only@example.com', 'Hello', 'Hi there');

        $this->assertNotNull(EmailLog::where('status', 'rejected')->first());
    }
}
