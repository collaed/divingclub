<?php

use App\Models\ThemeSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parent-child relationships for minors
        Schema::create('guardian_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('minor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship')->default('parent'); // parent, legal_guardian
            $table->timestamps();
            $table->unique(['guardian_user_id', 'minor_user_id']);
        });

        // Parental consent records
        Schema::create('parental_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->constrained('users'); // the guardian
            $table->string('consent_type'); // events, photos, medical, general
            $table->boolean('granted')->default(true);
            $table->string('document_path')->nullable(); // signed consent form upload
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['minor_user_id', 'consent_type']);
        });

        // Social media publish log
        Schema::create('social_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // facebook, instagram
            $table->morphs('publishable'); // event_photo, article, etc.
            $table->string('external_post_id')->nullable();
            $table->string('status'); // pending, published, failed
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Add gdpr_photo_consent flag directly on event_photos for quick checks
        if (! Schema::hasColumn('event_photos', 'gdpr_consent')) {
            Schema::table('event_photos', function (Blueprint $table) {
                $table->boolean('gdpr_consent')->default(false)->after('approved');
            });
        }

        // Retention policy setting
        ThemeSetting::firstOrCreate(
            ['key' => 'audit_retention_months'],
            ['value' => '24']
        );
        ThemeSetting::firstOrCreate(
            ['key' => 'fb_group_id'],
            ['value' => '']
        );
        ThemeSetting::firstOrCreate(
            ['key' => 'fb_group_is_closed'],
            ['value' => '0']
        );
        ThemeSetting::firstOrCreate(
            ['key' => 'social_auto_publish'],
            ['value' => '0']
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publish_logs');
        Schema::dropIfExists('parental_consents');
        Schema::dropIfExists('guardian_links');
        if (Schema::hasColumn('event_photos', 'gdpr_consent')) {
            Schema::table('event_photos', fn (Blueprint $t) => $t->dropColumn('gdpr_consent'));
        }
    }
};
