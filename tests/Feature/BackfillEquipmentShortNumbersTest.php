<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillEquipmentShortNumbersTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_plain_and_lettered_codes_from_the_name(): void
    {
        $plain = Equipment::factory()->create(['type' => 'tank', 'name' => 'Bloc Air #7', 'short_number' => null]);
        $lettered = Equipment::factory()->create(['type' => 'tank', 'name' => 'Bloc Air #M02', 'short_number' => null]);

        $this->artisan('app:backfill-equipment-short-numbers')->assertSuccessful();

        $this->assertSame('7', $plain->fresh()->short_number);
        $this->assertSame('M02', $lettered->fresh()->short_number);
    }

    public function test_leaves_serial_numbers_untouched(): void
    {
        $tampon = Equipment::factory()->create(['type' => 'tank', 'name' => 'Tampon #12623153', 'short_number' => null]);
        $oxygen = Equipment::factory()->create(['type' => 'tank', 'name' => 'Oxygen #428852', 'short_number' => null]);

        $this->artisan('app:backfill-equipment-short-numbers')->assertSuccessful();

        $this->assertNull($tampon->fresh()->short_number);
        $this->assertNull($oxygen->fresh()->short_number);
    }

    public function test_does_not_overwrite_an_existing_short_number(): void
    {
        $equipment = Equipment::factory()->create(['type' => 'tank', 'name' => 'Bloc Air #7', 'short_number' => 'OLD']);

        $this->artisan('app:backfill-equipment-short-numbers')->assertSuccessful();

        $this->assertSame('OLD', $equipment->fresh()->short_number);
    }

    public function test_skips_a_code_that_would_collide_with_another_item_of_the_same_type(): void
    {
        Equipment::factory()->create(['type' => 'tank', 'name' => 'Old label', 'short_number' => '7']);
        $duplicate = Equipment::factory()->create(['type' => 'tank', 'name' => 'Bloc Air #7', 'short_number' => null]);

        $this->artisan('app:backfill-equipment-short-numbers')->assertSuccessful();

        $this->assertNull($duplicate->fresh()->short_number);
    }

    public function test_dry_run_previews_without_saving(): void
    {
        $equipment = Equipment::factory()->create(['type' => 'tank', 'name' => 'Bloc Air #7', 'short_number' => null]);

        $this->artisan('app:backfill-equipment-short-numbers --dry-run')->assertSuccessful();

        $this->assertNull($equipment->fresh()->short_number);
    }

    public function test_a_different_type_with_the_same_code_does_not_collide(): void
    {
        Equipment::factory()->create(['type' => 'tank', 'name' => 'Old tank', 'short_number' => '7']);
        $bcd = Equipment::factory()->create(['type' => 'bcd', 'name' => 'Gilet #7', 'short_number' => null]);

        $this->artisan('app:backfill-equipment-short-numbers')->assertSuccessful();

        $this->assertSame('7', $bcd->fresh()->short_number);
    }
}
