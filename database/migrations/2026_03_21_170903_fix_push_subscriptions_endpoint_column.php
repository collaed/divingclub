<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('endpoint', 500)->change();
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unique('endpoint');
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['endpoint']);
            $table->text('endpoint')->change();
        });
    }
};
