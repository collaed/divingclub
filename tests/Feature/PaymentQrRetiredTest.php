<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class PaymentQrRetiredTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_removed_payment_qr_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('qr.sepa'));
        $this->assertFalse(Route::has('qr.sepa.public'));
        $this->assertFalse(Route::has('qr.payment.signed'));
        $this->assertFalse(Route::has('payment.verify'));
    }

    public function test_removed_payment_qr_urls_return_404(): void
    {
        $this->get('/qr/sepa-public?amount=10&communication=X')->assertNotFound();
        $this->get('/qr/payment?amount=10')->assertNotFound();
        $this->get('/pay/verify')->assertNotFound();
    }

    public function test_vcard_and_federation_qr_routes_still_exist(): void
    {
        $this->assertTrue(Route::has('qr.vcard'));
        $this->assertTrue(Route::has('qr.federation'));
    }

    public function test_dues_page_renders_without_payment_qr(): void
    {
        $res = $this->get(route('dues.show'))->assertOk();
        $res->assertDontSee('Payment QR');
        $res->assertDontSee('/qr/payment');
    }
}
