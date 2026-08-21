<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\SeedsRoles;
use Tests\TestCase;

class NewsletterFontTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRoles;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->createBureauUser();
    }

    public function test_bureau_can_set_newsletter_font_clean(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['newsletter_font' => 'clean'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('clean', ThemeSetting::get('newsletter_font'));
    }

    public function test_bureau_can_set_newsletter_font_classic(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['newsletter_font' => 'classic'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('classic', ThemeSetting::get('newsletter_font'));
    }

    public function test_bureau_can_set_newsletter_font_sharp(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['newsletter_font' => 'sharp'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('sharp', ThemeSetting::get('newsletter_font'));
    }

    public function test_bureau_can_set_newsletter_font_modern(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['newsletter_font' => 'modern'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals('modern', ThemeSetting::get('newsletter_font'));
    }

    public function test_invalid_newsletter_font_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.theme.update'), ['newsletter_font' => 'comic_sans'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull(ThemeSetting::get('newsletter_font'));
    }

    public function test_guest_cannot_set_newsletter_font(): void
    {
        $this->post(route('admin.settings.theme.update'), ['newsletter_font' => 'clean'])
            ->assertRedirect(route('login'));
    }

    public function test_settings_page_shows_newsletter_font_selector(): void
    {
        ThemeSetting::set('newsletter_font', 'sharp');

        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('newsletter_font')
            ->assertSee('Sharp');
    }
}
