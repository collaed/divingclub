<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Certification levels lookup table
        Schema::create('certification_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('federation_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);           // e.g. "N1", "OWD", "P1"
            $t->string('name');                // e.g. "Niveau 1", "Open Water Diver"
            $t->string('category', 30);        // diver, instructor, specialty
            $t->unsignedSmallInteger('rank')->default(0); // hierarchy within federation
            $t->string('equivalence_group', 30)->nullable(); // cross-federation equivalence
            $t->timestamps();
            $t->unique(['federation_id', 'code']);
        });

        // User certification levels (many-to-many)
        Schema::create('user_certification_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('certification_level_id')->constrained()->cascadeOnDelete();
            $t->date('obtained_date')->nullable();
            $t->boolean('is_primary')->default(false); // user's preferred display cert
            $t->unsignedInteger('display_priority')->default(0); // learned from user behavior
            $t->timestamps();
            $t->unique(['user_id', 'certification_level_id']);
        });

        // WhatsApp group link on events and season patterns
        Schema::table('events', function (Blueprint $t) {
            $t->string('whatsapp_group_url')->nullable()->after('participant_email');
        });
        Schema::table('season_patterns', function (Blueprint $t) {
            $t->string('whatsapp_group_url')->nullable()->after('color_hex');
        });

        // Theme settings (key-value, DB-driven)
        Schema::create('theme_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 80)->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('whatsapp_group_url'));
        Schema::table('season_patterns', fn (Blueprint $t) => $t->dropColumn('whatsapp_group_url'));
        Schema::dropIfExists('user_certification_levels');
        Schema::dropIfExists('certification_levels');
        Schema::dropIfExists('theme_settings');
    }
};
