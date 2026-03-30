<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->string('source_hash', 64)->nullable()->after('stale');
            $table->unsignedInteger('source_word_count')->nullable()->after('source_hash');
            $table->unsignedInteger('translated_word_count')->nullable()->after('source_word_count');
            $table->unsignedTinyInteger('retries')->default(0)->after('translated_word_count');
            $table->timestamp('flagged_at')->nullable()->after('retries');
            $table->string('flag_reason')->nullable()->after('flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->dropColumn(['source_hash', 'source_word_count', 'translated_word_count', 'retries', 'flagged_at', 'flag_reason']);
        });
    }
};
