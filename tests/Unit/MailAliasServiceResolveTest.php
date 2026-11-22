<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MemberDetail;
use App\Models\User;
use App\Services\MailAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class MailAliasServiceResolveTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_board_resolves_identically_to_bureau(): void
    {
        $user = User::factory()->create();
        MemberDetail::create(['user_id' => $user->id, 'first_name' => 'B', 'last_name' => 'Member', 'bureau_member' => true]);

        $bureau = MailAliasService::resolve('bureau@clubcep.eu');
        $board = MailAliasService::resolve('board@clubcep.eu');

        $this->assertSame($bureau, $board);
        $this->assertContains($user->primary_email, $board['emails']);
    }
}
