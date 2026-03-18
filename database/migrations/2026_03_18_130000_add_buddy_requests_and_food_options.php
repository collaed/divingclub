<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buddy requests — "looking for buddies" board
        Schema::create('buddy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dive_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location_text')->nullable(); // free text if no dive site
            $table->date('dive_date');
            $table->string('dive_time')->nullable(); // e.g. "morning", "10:00"
            $table->string('need_type'); // buddy, guide, dp (directeur de plongée)
            $table->text('description')->nullable();
            $table->integer('max_depth')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Responses to buddy requests
        Schema::create('buddy_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buddy_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('status')->default('interested'); // interested, confirmed, declined
            $table->timestamps();
            $table->unique(['buddy_request_id', 'user_id']);
        });

        // Add food/restaurants field to dive sites
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->text('food_options')->nullable()->after('facilities');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buddy_responses');
        Schema::dropIfExists('buddy_requests');
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn('food_options');
        });
    }
};
