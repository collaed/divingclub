<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->string('non_member_name')->nullable()->after('user_id');
        });

        // Make user_id nullable for non-member registrations
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropColumn('non_member_name');
        });
    }
};
