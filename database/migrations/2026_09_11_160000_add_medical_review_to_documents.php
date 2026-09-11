<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medical certificate review: reject (mirrors the existing verify columns)
 * plus a comment shared by the "validate with comments" and "reject"
 * actions — a document is only ever in one of those states at a time, so
 * one column covers both. Also a small admin-configurable library of
 * predefined comments reviewers can pick from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->timestamp('rejected_at')->nullable()->after('verified_at');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable()->after('rejected_by');
        });

        Schema::create('medical_review_comments', function (Blueprint $table): void {
            $table->id();
            $table->string('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'review_comment']);
        });
        Schema::dropIfExists('medical_review_comments');
    }
};
