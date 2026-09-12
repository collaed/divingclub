<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_records', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable()->after('ip_address');
            $table->string('country_name', 60)->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('login_records', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'country_name']);
        });
    }
};
