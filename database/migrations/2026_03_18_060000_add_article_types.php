<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('article_type', 30)->default('news')->after('slug');
            $table->foreignId('vote_id')->nullable()->after('author_id')->constrained('votes')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('is_public');

            $table->index('article_type');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['vote_id']);
            $table->dropIndex(['article_type']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['article_type', 'vote_id', 'expires_at']);
        });
    }
};
