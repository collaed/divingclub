<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('joomla_sortie_id')->nullable()->unique()->after('id');
        });
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unsignedInteger('joomla_inscription_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('joomla_sortie_id'));
        Schema::table('event_registrations', fn (Blueprint $t) => $t->dropColumn('joomla_inscription_id'));
    }
};
