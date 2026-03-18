<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trial dive appointment requests
        Schema::create('trial_requests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('preferred_date')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('confirmed_date')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        // Safety documents per dive site (link to library folder)
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->string('safety_docs_folder')->nullable()->after('site_plan_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_requests');
        Schema::table('dive_sites', function (Blueprint $table) {
            $table->dropColumn('safety_docs_folder');
        });
    }
};
