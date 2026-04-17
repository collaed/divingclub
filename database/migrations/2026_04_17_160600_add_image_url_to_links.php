<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('links', fn (Blueprint $t) => $t->dropColumn('image_url'));
    }
};
