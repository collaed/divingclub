<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federations', function (Blueprint $table) {
            $table->id();
            $table->string('acronym')->unique();
            $table->string('full_name');
            $table->timestamps();
        });

        Schema::create('member_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('birth_name')->nullable();
            $table->string('nationality')->nullable();
            $table->string('phone_private')->nullable();
            $table->string('phone_office')->nullable();
            $table->string('phone_mobile')->nullable();
            $table->enum('sex', ['M', 'F', 'X'])->nullable();
            $table->year('adhesion_year')->nullable();
            $table->boolean('bureau_member')->default(false);
            $table->boolean('active_instructor')->default(false);
            $table->string('cep_email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();
            $table->date('brevet_date')->nullable();
            $table->unsignedInteger('dive_count')->default(0);
            $table->string('certification_level')->nullable();
            $table->json('other_certifications')->nullable();
            $table->json('training_enrollments')->nullable();
            $table->string('preferred_language', 5)->default('en');
            $table->json('cotisation_years')->nullable();
            $table->string('bcd_size')->nullable();
            $table->text('bcd_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('member_licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('federation_id')->constrained('federations');
            $table->string('licence_number')->nullable();
            $table->string('federation_key')->nullable();
            $table->date('licence_request_date')->nullable();
            $table->boolean('licence_request_pending')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'federation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_licences');
        Schema::dropIfExists('member_details');
        Schema::dropIfExists('federations');
    }
};
