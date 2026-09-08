<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('p1')]
class SessionExpiredHandlingTest extends TestCase
{
    public function test_expired_csrf_token_redirects_back_with_a_message_for_web_requests(): void
    {
        Route::middleware('web')->get('/__csrf_expired_web', fn () => throw new TokenMismatchException);

        $this->from('/profile')
            ->get('/__csrf_expired_web')
            ->assertRedirect('/profile')
            ->assertSessionHas('error');
    }

    public function test_expired_csrf_token_returns_419_json_for_api_clients(): void
    {
        Route::middleware('web')->get('/__csrf_expired_json', fn () => throw new TokenMismatchException);

        $this->getJson('/__csrf_expired_json')
            ->assertStatus(419)
            ->assertJsonStructure(['message']);
    }
}
