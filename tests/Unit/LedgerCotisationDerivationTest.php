<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\LedgerTransaction;
use App\Services\LedgerClassificationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * LedgerClassificationService::deriveCotisationDetails() — reading the
 * category and insurance option straight from the bank communication text,
 * which for this club is usually the member typing back the club's own
 * payment instructions almost verbatim. Real examples sampled from
 * production, see CHANGELOG.
 */
#[Group('p1')]
class LedgerCotisationDerivationTest extends TestCase
{
    private function tx(string $communication): LedgerTransaction
    {
        return new LedgerTransaction(['communication_1' => $communication]);
    }

    private function service(): LedgerClassificationService
    {
        return app(LedgerClassificationService::class);
    }

    public function test_reads_category_and_top_insurance_from_real_wording(): void
    {
        $result = $this->service()->deriveCotisationDetails($this->tx('Matti VIHERLAIHO Cotisation 2026 Fonctionnaire Loisir 1 Top'));

        $this->assertSame('fonctionnaire', $result['category']);
        $this->assertSame('loisir1top', $result['insurance']);
        $this->assertNotNull($result['categoryLabel']);
        $this->assertNotNull($result['insuranceLabel']);
    }

    public function test_top_insurance_is_not_mistaken_for_the_base_option(): void
    {
        $result = $this->service()->deriveCotisationDetails($this->tx('Cotisation Membre externe+Licence +Assur. Loisir2 top'));

        $this->assertSame('externe', $result['category']);
        $this->assertSame('loisir2top', $result['insurance']);
    }

    public function test_reads_sympathisant_with_no_insurance_mentioned(): void
    {
        $result = $this->service()->deriveCotisationDetails($this->tx('Tomas PAZITKA Cotisation 2026 Sympathisant'));

        $this->assertSame('sympathisant', $result['category']);
        $this->assertNull($result['insurance']);
    }

    public function test_handles_a_common_typo_in_enfant(): void
    {
        $result = $this->service()->deriveCotisationDetails($this->tx('Jeanne Sira DIA Cotisation 2026 Enf ant Loisir 1'));

        $this->assertSame('enfant', $result['category']);
        $this->assertSame('loisir1', $result['insurance']);
    }

    public function test_vague_text_derives_nothing_rather_than_guessing(): void
    {
        $result = $this->service()->deriveCotisationDetails($this->tx('COTISATION 2025 2026'));

        $this->assertNull($result['category']);
        $this->assertNull($result['insurance']);
    }
}
