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

        MailSendStat::create(['date' => today(), 'provider' => 'resend_primary', 'count' => 98]);
        MailSendStat::create(['date' => today(), 'provider' => 'resend_secondary', 'count' => 98]);
        MailSendStat::create(['date' => today(), 'provider' => 'mailjet', 'count' => 200]);

        $this->assertSame('brevo', MailBalancer::nextProvider());

        MailBalancer::configureForNext();
        $this->assertSame('brevo', config('mail.default'));
    }

    /**
     * Real usage always pairs nextProvider() with recordSend() for the same
     * send (see AppServiceProvider's MessageSending listener) — rotation is
     * derived from those durable counts, so it must advance them each pick
     * to prove out cycling rather than looping forever on the same provider.
     */
    public function test_next_provider_round_robins_instead_of_filling_the_first_provider(): void
    {
        $picks = [];
        for ($i = 0; $i < 4; $i++) {
            $provider = MailBalancer::nextProvider();
            $picks[] = $provider;
            MailBalancer::recordSend($provider);
        }

        $this->assertSame(['resend_primary', 'resend_secondary', 'mailjet', 'brevo'], $picks);
    }

    public function test_round_robin_skips_a_provider_that_hits_its_daily_limit_mid_rotation(): void
    {
        MailSendStat::create(['date' => today(), 'provider' => 'resend_secondary', 'count' => 98]);

        $picks = [];
        for ($i = 0; $i < 4; $i++) {
            $provider = MailBalancer::nextProvider();
            $picks[] = $provider;
            MailBalancer::recordSend($provider);
        }

        // resend_secondary is exhausted throughout, so it never appears.
        $this->assertSame(['resend_primary', 'mailjet', 'brevo', 'resend_primary'], $picks);
    }

    public function test_rotation_survives_a_cache_flush(): void
    {
        // A deploy runs `artisan optimize:clear`, which flushes the cache —
        // rotation state must live entirely in the database to survive that.
        MailSendStat::create(['date' => today(), 'provider' => 'resend_primary', 'count' => 5]);
        Cache::flush();

        $this->assertSame('resend_secondary', MailBalancer::nextProvider());
    }
}
