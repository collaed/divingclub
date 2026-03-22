<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_registrations', function (Blueprint $table) {
            $table->string('external_member_iban', 34)->nullable()->after('external_member_emergency_contact');
        });
    }

    public function down(): void
    {
        Schema::table('external_registrations', function (Blueprint $table) {
            $table->dropColumn('external_member_iban');
        });
    }
};
