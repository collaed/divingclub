<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\ProcessTranslations;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class ProcessTranslationsTimeoutTest extends TestCase
{
    /**
     * A long, richly-formatted article translates via Cloudflare through
     * one sequential HTTP call per text segment — on 2026-09-15 this took
     * 163s for a real article, killed and endlessly retried every hour by
     * Horizon's default 60s worker timeout. The job must override it.
     */
    public function test_timeout_is_generous_enough_for_a_multi_segment_translation(): void
    {
        $job = new ProcessTranslations;

        $this->assertGreaterThanOrEqual(300, $job->timeout);
    }
}
