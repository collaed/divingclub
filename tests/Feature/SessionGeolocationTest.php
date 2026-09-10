<?php

namespace Tests\Feature;

use App\Jobs\ResolveLoginGeo;
use App\Models\LoginRecord;
use App\Support\GeoLocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p2')]
class SessionGeolocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_fills_country_from_a_public_ip(): void
    {
        Http::fake(['ipwho.is/*' => Http::response(['success' => true, 'country_code' => 'be', 'country' => 'Belgium'])]);

        $rec = LoginRecord::create(['user_id' => null, 'ip_address' => '81.240.1.1', 'guard' => 'web', 'remember' => false]);

        (new ResolveLoginGeo($rec->id))->handle(app(GeoLocator::class));

        $this->assertSame('BE', $rec->fresh()->country_code);
        $this->assertSame('Belgium', $rec->fresh()->country_name);
    }

    public function test_private_and_missing_ips_are_left_null(): void
    {
        Http::fake();

        $priv = LoginRecord::create(['user_id' => null, 'ip_address' => '192.168.1.5', 'guard' => 'web', 'remember' => false]);
        $none = LoginRecord::create(['user_id' => null, 'ip_address' => null, 'guard' => 'web', 'remember' => false]);

        (new ResolveLoginGeo($priv->id))->handle(app(GeoLocator::class));
        (new ResolveLoginGeo($none->id))->handle(app(GeoLocator::class));

        $this->assertNull($priv->fresh()->country_code);
        $this->assertNull($none->fresh()->country_code);
        Http::assertNothingSent();
    }

    public function test_flag_helper(): void
    {
        $this->assertSame('🇧🇪', GeoLocator::flag('BE'));
        $this->assertSame('🇱🇺', GeoLocator::flag('lu'));
        $this->assertSame('', GeoLocator::flag(null));
        $this->assertSame('', GeoLocator::flag('XYZ'));
    }
}
