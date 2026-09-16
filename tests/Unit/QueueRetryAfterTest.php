<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ProcessTranslations;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * The redis connection's retry_after must exceed every job's own $timeout —
 * otherwise Redis considers a still-running job "lost" and redelivers it to
 * another worker before it can finish, which (with Horizon's tries=1
 * supervisor config) turns into an immediate, permanent
 * MaxAttemptsExceededException rather than a real retry. This is exactly
 * what happened to ProcessTranslations on 2026-09-16: raising its $timeout
 * to 300s alone didn't fix the underlying hourly failures, because
 * retry_after was still 90s.
 */
#[Group('p1')]
class QueueRetryAfterTest extends TestCase
{
    public function test_redis_retry_after_exceeds_the_longest_job_timeout(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');
        $job = new ProcessTranslations;

        $this->assertGreaterThan($job->timeout, $retryAfter);
    }
}
