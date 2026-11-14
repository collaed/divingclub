<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ThemeSetting;
use App\Services\MailAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailAliasServiceMailtoAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailto_address_falls_back_to_config_when_no_override_is_set(): void
    {
        config(['club.mail_address' => 'club@clubcep.eu']);

        $this->assertSame('club+bureau@clubcep.eu', MailAliasService::mailtoAddress('bureau'));
    }

    public function test_mailto_address_uses_the_admin_configured_base_when_set(): void
    {
        config(['club.mail_address' => 'club@clubcep.eu']);
        ThemeSetting::set('mail_inbound_address', 'inbound@newdomain.eu');

        $this->assertSame('inbound+bureau@newdomain.eu', MailAliasService::mailtoAddress('bureau'));
    }
}
