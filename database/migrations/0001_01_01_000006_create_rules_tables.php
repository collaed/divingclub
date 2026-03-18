<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_compliance_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->unsignedInteger('age_bracket_low');
            $table->unsignedInteger('age_bracket_high');
            $table->string('cert_type'); // gp, ent, cardio, ophthalmologist, other
            $table->unsignedInteger('validity_months');
            $table->timestamps();
        });

        Schema::create('equipment_maintenance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('equipment_type');
            $table->string('maintenance_name');
            $table->unsignedInteger('interval_months');
            $table->boolean('is_mandatory')->default(true);
            $table->string('regulation_reference')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_rules');
        Schema::dropIfExists('medical_compliance_rules');
    }
};
