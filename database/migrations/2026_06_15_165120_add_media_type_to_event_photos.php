<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table): void {
            $table->string('mime_type', 50)->nullable()->after('path');
            $table->unsignedSmallInteger('duration')->nullable()->after('mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table): void {
            $table->dropColumn(['mime_type', 'duration']);
        });
    }
};
