<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->string('locale', 5);
            $t->string('title');
            $t->longText('body');
            $t->boolean('auto_translated')->default(true);
            $t->timestamps();
            $t->unique(['article_id', 'locale']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('preferred_locale', 5)->nullable()->after('status_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_translations');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('preferred_locale'));
    }
};
