<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('locally_modified_at')->nullable();
        });

        Schema::table('member_details', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('locally_modified_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('locally_modified_at')->nullable();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->json('counts')->nullable();
            $table->json('conflicts')->nullable();
            $table->string('status', 20)->default('running');
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');

        foreach (['users', 'member_details', 'events'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['synced_at', 'locally_modified_at']);
            });
        }
    }
};
