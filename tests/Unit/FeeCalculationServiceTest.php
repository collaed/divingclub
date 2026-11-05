<?php

namespace Tests\Unit;

use App\Models\MemberDetail;
use App\Models\MembershipFeeComponent;
use App\Models\MemberStatus;
use App\Models\User;
use App\Services\FeeCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p0')]
class FeeCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_communication_is_plain_words_a_member_would_type_back(): void
    {
        $service = new FeeCalculationService;
        $status = MemberStatus::create(['name' => 'Externe', 'slug' => 'externe']);
        $user = new User(['primary_email' => 'j@test.com']);
        $user->id = 42;
        $user->setRelation('detail', new MemberDetail(['last_name' => 'Dupont', 'first_name' => 'Jean']));
        $user->setRelation('status', $status);

        $result = $service->buildCommunication($user, '2026', [], $status);

        $this->assertSame('DUPONT JEAN Cotisation 2026 Externe', $result);
    }

    public function test_build_communication_spells_out_the_insurance_tier(): void
    {
        MembershipFeeComponent::create([
            'name' => 'Assurance Loisir 1 Top', 'slug' => 'ass_loisir1top',
            'kind' => MembershipFeeComponent::KIND_ASSURANCE, 'amount' => 48, 'is_optional' => true,
        ]);
        $service = new FeeCalculationService;
        $status = MemberStatus::create(['name' => 'Fonctionnaire', 'slug' => 'fonctionnaire']);
        $user = new User(['primary_email' => 'a@test.com']);
        $user->id = 7;
        $user->setRelation('detail', new MemberDetail(['last_name' => 'Smith', 'first_name' => 'Alice']));
        $user->setRelation('status', $status);

        $result = $service->buildCommunication($user, '2026', ['ass_loisir1top'], $status);

        $this->assertSame('SMITH ALICE Cotisation 2026 Fonctionnaire Loisir 1 Top', $result);
    }

    public function test_build_communication_no_detail_or_status_still_reads_cleanly(): void
    {
        $service = new FeeCalculationService;
        $user = new User(['primary_email' => 'x@test.com']);
        $user->id = 1;
        $user->setRelation('detail', null);
        $user->setRelation('status', null);

        $result = $service->buildCommunication($user, '2026', []);

        $this->assertSame('Cotisation 2026', $result);
    }
}
