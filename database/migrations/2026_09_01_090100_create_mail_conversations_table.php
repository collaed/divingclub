<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('initiator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('external_email');
            $table->string('external_name')->nullable();
            $table->string('token')->unique();
            $table->string('sas_alias');
            $table->string('subject')->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['initiator_user_id', 'external_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_conversations');
    }
};
