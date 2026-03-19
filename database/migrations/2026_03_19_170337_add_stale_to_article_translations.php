<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->boolean('stale')->default(false)->after('auto_translated');
        });
    }

    public function down(): void
    {
        Schema::table('article_translations', function (Blueprint $table) {
            $table->dropColumn('stale');
        });
    }
};
