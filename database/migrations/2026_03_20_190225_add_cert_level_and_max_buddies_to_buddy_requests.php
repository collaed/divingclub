<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buddy_requests', function (Blueprint $table) {
            $table->string('desired_cert_level')->nullable()->after('max_depth');
            $table->unsignedTinyInteger('max_buddies')->nullable()->after('desired_cert_level');
        });
    }

    public function down(): void
    {
        Schema::table('buddy_requests', function (Blueprint $table) {
            $table->dropColumn(['desired_cert_level', 'max_buddies']);
        });
    }
};
