<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Article comments (threaded)
        Schema::create('article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('article_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['article_id', 'created_at']);
        });

        // Multi-select ballots: allow multiple endorsements per voter
        // Add unique constraint change: for trip proposals, one user can vote for multiple options
        Schema::table('votes', function (Blueprint $table) {
            $table->boolean('allow_multiple')->default(false)->after('mode');
            $table->boolean('allow_change')->default(true)->after('allow_multiple');
            $table->boolean('is_public')->default(false)->after('allow_change');
        });

        // Article type background colors (admin-configurable)
        // Stored in theme_settings as article_bg_<type> keys — no schema change needed

        // Article gallery layout
        Schema::table('article_images', function (Blueprint $table) {
            $table->string('caption')->nullable()->after('alt_text');
            $table->string('layout_hint', 20)->default('full')->after('caption'); // full, half, third
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_comments');
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['allow_multiple', 'allow_change', 'is_public']);
        });
        Schema::table('article_images', function (Blueprint $table) {
            $table->dropColumn(['caption', 'layout_hint']);
        });
    }
};
