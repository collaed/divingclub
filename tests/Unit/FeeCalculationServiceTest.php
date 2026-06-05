<?php

namespace Tests\Unit;

use App\Models\MemberDetail;
use App\Models\User;
use App\Services\FeeCalculationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p0')]
class FeeCalculationServiceTest extends TestCase
{
    public function test_build_communication_format(): void
    {
        $service = new FeeCalculationService;
        $user = new User(['primary_email' => 'j@test.com']);
        $user->id = 42;
        $detail = new MemberDetail(['last_name' => 'Dupont', 'first_name' => 'Jean']);
        $user->setRelation('detail', $detail);

        $result = $service->buildCommunication($user, '2026', []);

        $this->assertStringContainsString('2026', $result);
        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString('DUPONT JEAN', $result);
    }

    public function test_build_communication_with_optionals(): void
    {
        $service = new FeeCalculationService;
        $user = new User(['primary_email' => 'a@test.com']);
        $user->id = 7;
        $detail = new MemberDetail(['last_name' => 'Smith', 'first_name' => 'Alice']);
        $user->setRelation('detail', $detail);

        $result = $service->buildCommunication($user, '2026', ['insurance', 'double_affiliation']);

        $this->assertStringContainsString('+insurance+double_affiliation', $result);
    }

    public function test_build_communication_no_detail(): void
    {
        $service = new FeeCalculationService;
        $user = new User(['primary_email' => 'x@test.com']);
        $user->id = 1;
        $user->setRelation('detail', null);

        $result = $service->buildCommunication($user, '2026', []);

        $this->assertStringContainsString('2026', $result);
        $this->assertStringContainsString('1', $result);
    }
}
