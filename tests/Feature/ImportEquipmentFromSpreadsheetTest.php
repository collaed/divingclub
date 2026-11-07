<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportEquipmentFromSpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private const REGULATORS_CSV = <<<'CSV'
        "Type DETENDEURS DIN","No ","Marque ","Modèle ","No série ","Marque Octobpus ","No série Octopus ","Révisions ","Date de fabrication ","Pointage Inventaire "
        "DIN","8","Aqua Lung","Titan","EN250 7092148","Aqualung","A085508","octobre 2025","2007","19/5/2021"
        "DIN","14","Aqua Lung","Legend","A087986","Aqualung","A086644","avril 2023","2010","19/5/2021"
        "DETENDEURS ETRIER","","","","","","","","",""
        "OCTOPUS SEUL","","","","","","","","",""
        "Octopus","","","","","Aqualung","A088603","","2010",""
        "Hors Inventaire","","","","","","","","",""
        "Etrier","2","Aqua Lung","Legend","EN250 7094070","","","","","19/5/2021"
        CSV;

    private const JACKETS_CSV = <<<'CSV'
        "GILETS L","N° CEP ","Marque ","Type ","Couleur ","Date d'achat ","Entretien / date ","Pointage Inventaire "
        "Gilet L","L5","Aqualung","","","2014","","19/5/2021"
        "GILETS M","","","","","","",""
        "Gilet M","M7","Scubapro","Top 100","","2001","2002","19/5/2021"
        "GILETS XXXS","","","","","","",""
        "Gilet XXXS","XXXS32","Mares","Scuba Ranger","","2021","","19/5/2021"
        "HARNAIS","","","","","","",""
        "Harnais","","Harness","Harness","","","","19/5/2021"
        "Hors Inventaire","","","","","","",""
        "Gilet L HI","L3","Spiro","Octopus SBC","Jaune","1994","2001",""
        CSV;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*sheet=D*tendeurs*' => Http::response(self::REGULATORS_CSV, 200),
            '*sheet=Jackets*' => Http::response(self::JACKETS_CSV, 200),
        ]);
    }

    public function test_imports_active_regulators_and_bcds_with_short_numbers_set(): void
    {
        $this->artisan('app:import-equipment-from-spreadsheet')->assertSuccessful();

        $this->assertSame(2, Equipment::where('type', 'regulator')->count());
        $this->assertSame(3, Equipment::where('type', 'bcd')->count());

        $this->assertDatabaseHas('equipment', ['type' => 'regulator', 'short_number' => '8', 'brand' => 'Aqua Lung']);
        $this->assertDatabaseHas('equipment', ['type' => 'bcd', 'short_number' => 'M7', 'size' => 'm']);
        $this->assertDatabaseHas('equipment', ['type' => 'bcd', 'short_number' => 'XXXS32', 'size' => 'xxxs']);
    }

    public function test_skips_retired_and_non_item_rows(): void
    {
        $this->artisan('app:import-equipment-from-spreadsheet')->assertSuccessful();

        $this->assertDatabaseMissing('equipment', ['short_number' => '2']); // retired Etrier
        $this->assertDatabaseMissing('equipment', ['short_number' => 'L3']); // retired BCD
        $this->assertSame(0, Equipment::where('name', 'like', '%Harness%')->count());
        $this->assertSame(0, Equipment::where('name', 'like', '%Octopus%')->count()); // no short_number to key on
    }

    public function test_does_not_duplicate_an_existing_short_number_of_the_same_type(): void
    {
        Equipment::factory()->create(['type' => 'regulator', 'short_number' => '8']);

        $this->artisan('app:import-equipment-from-spreadsheet')->assertSuccessful();

        $this->assertSame(1, Equipment::where('type', 'regulator')->where('short_number', '8')->count());
    }

    public function test_dry_run_previews_without_saving(): void
    {
        $this->artisan('app:import-equipment-from-spreadsheet --dry-run')->assertSuccessful();

        $this->assertSame(0, Equipment::count());
    }
}
