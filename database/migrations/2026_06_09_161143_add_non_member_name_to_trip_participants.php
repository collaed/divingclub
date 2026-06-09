<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->string('non_member_name')->nullable()->after('user_id');
        });

        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('trip_participants', function (Blueprint $table): void {
            $table->dropColumn('non_member_name');
        });
    }
};
