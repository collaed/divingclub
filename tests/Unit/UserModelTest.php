<?php

namespace Tests\Unit;

use App\Models\MemberDetail;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    public function test_name_returns_full_name_when_detail_exists(): void
    {
        $user = new User(['username' => 'jdoe', 'primary_email' => 'j@example.com']);
        $detail = new MemberDetail(['first_name' => 'John', 'last_name' => 'Doe']);
        $user->setRelation('detail', $detail);

        $this->assertSame('John Doe', $user->name);
    }

    public function test_name_falls_back_to_username(): void
    {
        $user = new User(['username' => 'jdoe', 'primary_email' => 'j@example.com']);
        $user->setRelation('detail', null);

        $this->assertSame('jdoe', $user->name);
    }

    public function test_name_falls_back_to_email_when_no_username(): void
    {
        $user = new User(['username' => null, 'primary_email' => 'j@example.com']);
        $user->setRelation('detail', null);

        $this->assertSame('j@example.com', $user->name);
    }

    public function test_email_attribute_returns_primary_email(): void
    {
        $user = new User(['primary_email' => 'test@example.com']);

        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('test@example.com', $user->getEmailForPasswordReset());
        $this->assertSame('test@example.com', $user->getEmailForVerification());
    }

    public function test_has_dive_profile_true_when_complete(): void
    {
        $user = new User;
        $detail = new MemberDetail([
            'date_of_birth' => '1990-01-01',
            'sex' => 'M',
            'phone_mobile' => '+352123456',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+352654321',
        ]);
        $user->setRelation('detail', $detail);

        $this->assertTrue($user->hasDiveProfile());
    }

    public function test_has_dive_profile_false_when_missing_fields(): void
    {
        $user = new User;
        $detail = new MemberDetail([
            'date_of_birth' => '1990-01-01',
            'sex' => 'M',
            'phone_mobile' => null,
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
        ]);
        $user->setRelation('detail', $detail);

        $this->assertFalse($user->hasDiveProfile());
    }

    public function test_has_dive_profile_false_when_no_detail(): void
    {
        $user = new User;
        $user->setRelation('detail', null);

        $this->assertFalse($user->hasDiveProfile());
    }

    public function test_missing_dive_profile_fields_lists_all_when_no_detail(): void
    {
        $user = new User;
        $user->setRelation('detail', null);

        $this->assertCount(5, $user->missingDiveProfileFields());
    }

    public function test_missing_dive_profile_fields_empty_when_complete(): void
    {
        $user = new User;
        $detail = new MemberDetail([
            'date_of_birth' => '1990-01-01',
            'sex' => 'M',
            'phone_mobile' => '+352123456',
            'emergency_contact_name' => 'Jane',
            'emergency_contact_phone' => '+352654321',
        ]);
        $user->setRelation('detail', $detail);

        $this->assertEmpty($user->missingDiveProfileFields());
    }

    public function test_minor_always_has_photos_banned(): void
    {
        $user = new User;
        $detail = new MemberDetail([
            'date_of_birth' => Carbon::now()->subYears(15)->format('Y-m-d'),
            'public_photos_banned' => false,
        ]);
        $user->setRelation('detail', $detail);

        $this->assertTrue($user->hasPublicPhotosBanned());
    }

    public function test_adult_with_ban_flag_has_photos_banned(): void
    {
        $user = new User;
        $detail = new MemberDetail([
            'date_of_birth' => Carbon::now()->subYears(30)->format('Y-m-d'),
            'public_photos_banned' => true,
        ]);
        $user->setRelation('detail', $detail);

        $this->assertTrue($user->hasPublicPhotosBanned());
    }

    public function test_adult_without_ban_flag_not_banned(): void
    {
        $user = new User;
        $detail = new MemberDetail([
            'date_of_birth' => Carbon::now()->subYears(30)->format('Y-m-d'),
            'public_photos_banned' => false,
        ]);
        $user->setRelation('detail', $detail);

        $this->assertFalse($user->hasPublicPhotosBanned());
    }

    public function test_is_minor_true_for_child(): void
    {
        $user = new User;
        $detail = new MemberDetail(['date_of_birth' => Carbon::now()->subYears(12)->format('Y-m-d')]);
        $user->setRelation('detail', $detail);

        $this->assertTrue($user->isMinor());
    }

    public function test_is_minor_false_for_adult(): void
    {
        $user = new User;
        $detail = new MemberDetail(['date_of_birth' => Carbon::now()->subYears(25)->format('Y-m-d')]);
        $user->setRelation('detail', $detail);

        $this->assertFalse($user->isMinor());
    }

    public function test_is_minor_false_when_no_dob(): void
    {
        $user = new User;
        $detail = new MemberDetail(['date_of_birth' => null]);
        $user->setRelation('detail', $detail);

        $this->assertFalse($user->isMinor());
    }
}
