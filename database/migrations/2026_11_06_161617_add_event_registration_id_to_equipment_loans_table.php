<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table): void {
            $table->foreignId('event_registration_id')->nullable()->after('user_id')
                ->constrained('event_registrations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_registration_id');
        });
    }
};
