<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_files', function (Blueprint $table): void {
            $table->timestamp('kanban_processed_at')->nullable();
            $table->string('kanban_skip_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table): void {
            $table->dropColumn(['kanban_processed_at', 'kanban_skip_reason']);
        });
    }
};
