<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_heartbeats', function (Blueprint $table) {
            $table->string('task')->primary();
            $table->timestamp('last_run_at');
            $table->boolean('success')->default(true);
            $table->text('message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_heartbeats');
    }
};
