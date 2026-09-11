<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Horizon silently runs zero workers for any APP_ENV not listed in
 * config/horizon.php's 'environments' (no error, no log — the master
 * process just never spawns a supervisor). Staging (APP_ENV=staging) was
 * missing here for a long time; queued jobs — including tracked-document
 * emails — piled up forever with no failure anywhere to notice.
 */
#[Group('p1')]
class HorizonEnvironmentConfigTest extends TestCase
{
    public function test_every_environment_this_app_actually_runs_in_has_a_supervisor(): void
    {
        $environments = require __DIR__.'/../../config/horizon.php';

        foreach (['production', 'staging', 'local'] as $env) {
            $this->assertArrayHasKey($env, $environments['environments'], "Horizon has no supervisor configured for APP_ENV={$env} — it would run with zero workers.");
            $this->assertGreaterThan(0, $environments['environments'][$env]['supervisor-1']['maxProcesses'] ?? 0);
        }
    }
}
