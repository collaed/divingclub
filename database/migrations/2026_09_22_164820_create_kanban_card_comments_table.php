<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A flat, chronological log on a kanban card — "did X", "waiting on Y" —
 * so progress is visible without changing the card's own title/context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_card_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kanban_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('kanban_card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_card_comments');
    }
};
