<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_licences', function (Blueprint $table) {
            $table->string('insurance_type', 50)->nullable()->after('licence_number');
            $table->date('medical_cert_expiry')->nullable()->after('insurance_type');
            $table->string('season', 20)->nullable()->after('medical_cert_expiry');
            $table->date('registration_date')->nullable()->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('member_licences', function (Blueprint $table) {
            $table->dropColumn(['insurance_type', 'medical_cert_expiry', 'season', 'registration_date']);
        });
    }
};
