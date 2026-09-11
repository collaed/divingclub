<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CloudflareAiUsageStat;
use App\Services\CloudflareUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class CloudflareUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.cloudflare.account_id' => 'acc123', 'services.cloudflare.api_token' => 'tok123']);
    }

    public function test_returns_empty_without_configured_credentials(): void
    {
        config(['services.cloudflare.account_id' => null, 'services.cloudflare.api_token' => null]);

        $rows = app(CloudflareUsageService::class)->fetchDailyUsage(today()->subDay(), today());

        $this->assertSame([], $rows);
    }

    public function test_parses_a_successful_graphql_response(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'data' => ['viewer' => ['accounts' => [[
                    'aiInferenceAdaptiveGroups' => [
                        ['count' => 5, 'dimensions' => ['date' => '2026-09-10', 'modelId' => '@cf/meta/m2m100-1.2b'], 'sum' => ['totalNeurons' => 42]],
                        ['count' => 2, 'dimensions' => ['date' => '2026-09-11', 'modelId' => '@cf/meta/llama-3.2-11b-vision-instruct'], 'sum' => ['totalNeurons' => 17]],
                    ],
                ]]]],
                'errors' => null,
            ], 200),
        ]);

        $rows = app(CloudflareUsageService::class)->fetchDailyUsage(today()->subDay(), today());

        $this->assertCount(2, $rows);
        $this->assertSame(['date' => '2026-09-10', 'model_id' => '@cf/meta/m2m100-1.2b', 'neurons' => 42, 'requests' => 5], $rows[0]);
    }

    public function test_returns_empty_when_the_api_reports_graphql_errors(): void
    {
        Http::fake(['api.cloudflare.com/*' => Http::response(['data' => null, 'errors' => [['message' => 'not authorized for that account']]], 200)]);

        $rows = app(CloudflareUsageService::class)->fetchDailyUsage(today()->subDay(), today());

        $this->assertSame([], $rows);
    }

    public function test_sync_command_upserts_rows_and_re_running_updates_them(): void
    {
        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'data' => ['viewer' => ['accounts' => [[
                    'aiInferenceAdaptiveGroups' => [
                        ['count' => 5, 'dimensions' => ['date' => '2026-09-10', 'modelId' => '@cf/meta/m2m100-1.2b'], 'sum' => ['totalNeurons' => 42]],
                    ],
                ]]]],
            ], 200),
        ]);

        Artisan::call('cloudflare:sync-usage');

        $this->assertSame(1, CloudflareAiUsageStat::count());
        $this->assertSame(42, CloudflareAiUsageStat::first()->neurons);

        Http::fake([
            'api.cloudflare.com/*' => Http::response([
                'data' => ['viewer' => ['accounts' => [[
                    'aiInferenceAdaptiveGroups' => [
                        ['count' => 8, 'dimensions' => ['date' => '2026-09-10', 'modelId' => '@cf/meta/m2m100-1.2b'], 'sum' => ['totalNeurons' => 60]],
                    ],
                ]]]],
            ], 200),
        ]);

        Artisan::call('cloudflare:sync-usage');

        $this->assertSame(1, CloudflareAiUsageStat::count());
        $this->assertSame(60, CloudflareAiUsageStat::first()->neurons);
    }

    public function test_history_sums_neurons_across_models_per_day_and_zero_fills(): void
    {
        CloudflareAiUsageStat::create(['date' => today(), 'model_id' => 'a', 'neurons' => 10, 'requests' => 1]);
        CloudflareAiUsageStat::create(['date' => today(), 'model_id' => 'b', 'neurons' => 5, 'requests' => 1]);

        $history = CloudflareAiUsageStat::history(60);

        $this->assertCount(60, $history['dates']);
        $this->assertSame(15, end($history['neurons']));
        $this->assertSame(0, $history['neurons'][0]);
    }
}
