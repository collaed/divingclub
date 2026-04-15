<?php

namespace Tests\Unit;

use App\Services\UddfService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @group p2
 */
class UddfServiceTest extends TestCase
{
    private UddfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UddfService;
    }

    // --- detectSafetyStop ---

    public function test_detect_safety_stop_true_when_held_at_5m(): void
    {
        // Descend to 20m, ascend, hold at 5m for 3 minutes
        $samples = [
            ['time' => 0, 'depth' => 0],
            ['time' => 120, 'depth' => 20],
            ['time' => 1200, 'depth' => 20],
            ['time' => 1260, 'depth' => 10],  // ascending
            ['time' => 1320, 'depth' => 5],   // safety stop start
            ['time' => 1380, 'depth' => 5],
            ['time' => 1440, 'depth' => 5],
            ['time' => 1500, 'depth' => 5],   // 180s at 5m
            ['time' => 1560, 'depth' => 0],
        ];

        $this->assertTrue($this->invokeDetectSafetyStop($samples));
    }

    public function test_detect_safety_stop_false_when_too_short(): void
    {
        // Only 90s at safety stop depth (< 120s threshold)
        $samples = [
            ['time' => 0, 'depth' => 0],
            ['time' => 120, 'depth' => 20],
            ['time' => 1200, 'depth' => 20],
            ['time' => 1260, 'depth' => 10],  // ascending
            ['time' => 1320, 'depth' => 5],
            ['time' => 1350, 'depth' => 5],   // 90s at 3-6m
            ['time' => 1410, 'depth' => 0],
        ];

        $this->assertFalse($this->invokeDetectSafetyStop($samples));
    }

    public function test_detect_safety_stop_false_when_no_ascent(): void
    {
        // Stays deep, never ascends through 3-6m zone
        $samples = [
            ['time' => 0, 'depth' => 0],
            ['time' => 120, 'depth' => 20],
            ['time' => 1200, 'depth' => 20],
            ['time' => 1260, 'depth' => 20],
        ];

        $this->assertFalse($this->invokeDetectSafetyStop($samples));
    }

    public function test_detect_safety_stop_at_3m_boundary(): void
    {
        $samples = [
            ['time' => 0, 'depth' => 0],
            ['time' => 120, 'depth' => 15],
            ['time' => 1200, 'depth' => 15],
            ['time' => 1260, 'depth' => 8],   // ascending
            ['time' => 1320, 'depth' => 3],   // at 3m
            ['time' => 1380, 'depth' => 3],
            ['time' => 1440, 'depth' => 3],
            ['time' => 1500, 'depth' => 3],   // 180s at 3m
            ['time' => 1560, 'depth' => 0],
        ];

        $this->assertTrue($this->invokeDetectSafetyStop($samples));
    }

    // --- parse ---

    public function test_parse_minimal_uddf(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<uddf xmlns="http://www.streit.cc/uddf/3.2/" version="3.2.1">
  <divesite>
    <site id="site1">
      <name>Test Lake</name>
      <geography>
        <latitude>49.5</latitude>
        <longitude>6.1</longitude>
        <location>Luxembourg</location>
      </geography>
      <maximumdepth>30</maximumdepth>
    </site>
  </divesite>
  <profiledata>
    <repetitiongroup>
      <dive>
        <informationbeforedive>
          <datetime>2026-03-15T10:00:00</datetime>
          <greatestdepth>25</greatestdepth>
          <diveduration>2400</diveduration>
          <link ref="site1"/>
        </informationbeforedive>
        <informationafterdive>
          <notes>Great visibility</notes>
        </informationafterdive>
      </dive>
    </repetitiongroup>
  </profiledata>
</uddf>
XML;

        $result = $this->service->parse($xml);

        $this->assertArrayHasKey('dives', $result);
        $this->assertArrayHasKey('sites', $result);
        $this->assertCount(1, $result['dives']);
        $this->assertCount(1, $result['sites']);

        $dive = $result['dives'][0];
        $this->assertSame(25.0, $dive['max_depth']);
        $this->assertSame(2400, $dive['duration_seconds']);
        $this->assertSame(40, $dive['duration_minutes']);
        $this->assertSame('site1', $dive['site_ref']);
        $this->assertSame('Great visibility', $dive['notes']);

        $site = $result['sites']['site1'];
        $this->assertSame('Test Lake', $site['name']);
        $this->assertSame(49.5, $site['latitude']);
        $this->assertSame(30.0, $site['max_depth']);
    }

    public function test_parse_dive_without_datetime_is_skipped(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<uddf xmlns="http://www.streit.cc/uddf/3.2/" version="3.2.1">
  <profiledata>
    <repetitiongroup>
      <dive>
        <informationbeforedive>
          <greatestdepth>10</greatestdepth>
        </informationbeforedive>
      </dive>
    </repetitiongroup>
  </profiledata>
</uddf>
XML;

        $result = $this->service->parse($xml);

        $this->assertEmpty($result['dives']);
    }

    public function test_parse_with_samples_extracts_depth_and_temp(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<uddf xmlns="http://www.streit.cc/uddf/3.2/" version="3.2.1">
  <profiledata>
    <repetitiongroup>
      <dive>
        <informationbeforedive>
          <datetime>2026-03-15T10:00:00</datetime>
        </informationbeforedive>
        <samples>
          <waypoint><divetime>0</divetime><depth>0</depth><temperature>288.15</temperature></waypoint>
          <waypoint><divetime>300</divetime><depth>15</depth><temperature>283.15</temperature></waypoint>
          <waypoint><divetime>600</divetime><depth>25</depth><temperature>280.15</temperature></waypoint>
          <waypoint><divetime>1200</divetime><depth>5</depth></waypoint>
          <waypoint><divetime>1500</divetime><depth>0</depth></waypoint>
        </samples>
      </dive>
    </repetitiongroup>
  </profiledata>
</uddf>
XML;

        $result = $this->service->parse($xml);
        $dive = $result['dives'][0];

        $this->assertSame(25.0, $dive['max_depth']);
        $this->assertSame(1500, $dive['duration_seconds']);
        $this->assertSame(7.0, $dive['min_temperature']); // 280.15K = 7°C
        $this->assertCount(5, $dive['samples']);
    }

    // --- Helpers ---

    private function invokeDetectSafetyStop(array $samples): bool
    {
        $method = new ReflectionMethod(UddfService::class, 'detectSafetyStop');

        return $method->invoke($this->service, $samples);
    }
}
