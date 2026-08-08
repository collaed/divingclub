<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vote_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('votes', function (Blueprint $table): void {
            $table->foreignId('vote_group_id')->nullable()->after('id')->constrained('vote_groups')->nullOnDelete();
        });

        Schema::table('vote_tokens', function (Blueprint $table): void {
            $table->foreignId('vote_group_id')->nullable()->after('id')->constrained('vote_groups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vote_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vote_group_id');
        });
        Schema::table('votes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vote_group_id');
        });
        Schema::dropIfExists('vote_groups');
    }
};
