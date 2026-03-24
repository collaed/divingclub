<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('month', 7); // 2026-03
            $table->string('background_image')->nullable(); // storage path
            $table->json('slots'); // [{article_id, position (1-5)}]
            $table->enum('status', ['draft', 'pending', 'approved', 'sent'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('newsletter_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->boolean('approved')->default(true);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['newsletter_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_approvals');
        Schema::dropIfExists('newsletters');
    }
};
