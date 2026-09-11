<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MailSendStat;
use App\Services\MailBalancer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class MailBalancerHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_send_persists_a_durable_row_per_provider_per_day(): void
    {
        MailBalancer::recordSend('resend_primary');
        MailBalancer::recordSend('resend_primary');
        MailBalancer::recordSend('mailjet');

        $this->assertSame(2, MailSendStat::where('provider', 'resend_primary')->where('date', today())->value('count'));
        $this->assertSame(1, MailSendStat::where('provider', 'mailjet')->where('date', today())->value('count'));
    }

    public function test_history_zero_fills_days_with_no_sends_and_windows_correctly(): void
    {
        MailSendStat::create(['date' => today(), 'provider' => 'resend_primary', 'count' => 3]);
        MailSendStat::create(['date' => today()->subDays(5), 'provider' => 'resend_primary', 'count' => 7]);
        MailSendStat::create(['date' => today()->subDays(100), 'provider' => 'resend_primary', 'count' => 99]);

        $history = MailBalancer::history(60);

        $this->assertCount(60, $history['dates']);
        $this->assertSame(today()->format('Y-m-d'), end($history['dates']));
        $this->assertSame(3, end($history['series']['resend_primary']));

        $fiveDaysAgoIndex = array_search(today()->subDays(5)->format('Y-m-d'), $history['dates'], true);
        $this->assertSame(7, $history['series']['resend_primary'][$fiveDaysAgoIndex]);

        // The 100-day-old row falls outside the 60-day window entirely.
        $this->assertNotContains(99, $history['series']['resend_primary']);
    }

    public function test_brevo_is_included_in_status_and_picked_once_other_providers_are_exhausted(): void
    {
        $this->assertContains('brevo', collect(MailBalancer::status())->pluck('provider')->all());

        $today = date('Y-m-d');
        Cache::put("mail_balance_{$today}_resend_primary", 98);
        Cache::put("mail_balance_{$today}_resend_secondary", 98);
        Cache::put("mail_balance_{$today}_mailjet", 200);

        $this->assertSame('brevo', MailBalancer::nextProvider());

        MailBalancer::configureForNext();
        $this->assertSame('brevo', config('mail.default'));
    }
}
