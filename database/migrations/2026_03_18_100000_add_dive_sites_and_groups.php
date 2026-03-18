<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dive sites — reusable location profiles
        Schema::create('dive_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->integer('max_depth')->nullable(); // metres
            $table->string('water_type')->nullable(); // sea, lake, quarry, river, pool
            $table->text('conditions')->nullable();
            $table->text('marine_life')->nullable();
            $table->text('safety_notes')->nullable();
            $table->text('access_notes')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Link events to dive sites
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('dive_site_id')->nullable()->after('whatsapp_group_url')->constrained()->nullOnDelete();
        });

        // Configurable rules for dive group composition
        Schema::create('dive_group_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // human-readable rule name
            $table->string('scope'); // federation acronym or 'global'
            $table->string('diver_condition'); // e.g. 'no_cert', 'max_rank:20', 'max_rank:40', 'any'
            $table->string('dive_mode'); // 'supervised', 'autonomous', 'training', 'certification'
            $table->integer('min_leader_rank'); // minimum rank of group leader
            $table->string('leader_category'); // 'instructor' or 'diver' (guide de palanquée = diver cat rank>=70)
            $table->integer('max_depth')->nullable(); // depth limit for this rule
            $table->integer('max_group_size')->default(4);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Dive groups (palanquées) per event
        Schema::create('dive_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable(); // "Palanquée 1", etc.
            $table->string('dive_mode')->default('supervised'); // supervised, autonomous, training, certification
            $table->integer('planned_depth')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Members of each dive group
        Schema::create('dive_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dive_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('diver'); // leader, diver
            $table->timestamps();
            $table->unique(['dive_group_id', 'user_id']);
        });

        // Add caption to event_photos if not present
        if (!Schema::hasColumn('event_photos', 'caption')) {
            // already exists
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dive_group_members');
        Schema::dropIfExists('dive_groups');
        Schema::dropIfExists('dive_group_rules');
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dive_site_id');
        });
        Schema::dropIfExists('dive_sites');
    }
};
