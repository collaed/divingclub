<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MailAliasService;
use Tests\TestCase;

class MailAliasForwardTest extends TestCase
{
    public function test_forward_alias_resolves_to_target_inbox(): void
    {
        $resolved = MailAliasService::resolve('sas.eddy@clubcep.eu');

        $this->assertNotNull($resolved);
        $this->assertContains('eddy.collart@gmail.com', $resolved['emails']);
        $this->assertSame('open', $resolved['auth_level']);
    }

    public function test_forward_alias_accepts_any_sender(): void
    {
        // An address that is not a known member must still be authorized to
        // send to a passthrough forward alias.
        $this->assertTrue(
            MailAliasService::isAuthorized('random-stranger@example.com', 'sas.eddy@clubcep.eu')
        );
    }

    public function test_forward_alias_works_with_plus_addressing(): void
    {
        $resolved = MailAliasService::resolve('cep+sas.eddy@clubcep.eu');

        $this->assertNotNull($resolved);
        $this->assertContains('eddy.collart@gmail.com', $resolved['emails']);
    }

    public function test_group_alias_still_rejects_unknown_sender(): void
    {
        // Regression guard: opening forward aliases must not open group aliases.
        $this->assertFalse(
            MailAliasService::isAuthorized('random-stranger@example.com', 'bureau@clubcep.eu')
        );
    }
}
