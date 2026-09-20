<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessTranslations;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Horizon force-kills a worker the autoscaler has told to terminate once the
 * supervisor timeout elapses. With a 60s timeout that killed ProcessTranslations
 * mid-run every hour, leaving it reserved until retry_after and then failed as
 * "attempted too many times" (staging /health: 50 failed jobs, status degraded).
 */
#[Group('p1')]
class HorizonTimeoutTest extends TestCase
{
    public function test_supervisor_timeout_covers_the_longest_job_and_stays_below_retry_after(): void
    {
        $timeout = (int) config('horizon.defaults.supervisor-1.timeout');

        $this->assertGreaterThanOrEqual((new ProcessTranslations)->timeout, $timeout);
        $this->assertLessThan((int) config('queue.connections.redis.retry_after'), $timeout);
    }
}
