<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_sets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('status_set_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('status_set_id')->constrained('status_sets')->cascadeOnDelete();
            $table->foreignId('member_status_id')->constrained('member_statuses')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['status_set_id', 'member_status_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'status_set_id')) {
                $table->foreignId('status_set_id')
                    ->nullable()
                    ->after('status_id')
                    ->constrained('status_sets')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'status_set_id')) {
                $table->dropConstrainedForeignId('status_set_id');
            }
        });

        Schema::dropIfExists('status_set_members');
        Schema::dropIfExists('status_sets');
    }
};
