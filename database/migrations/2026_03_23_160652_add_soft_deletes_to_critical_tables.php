<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'users',
        'member_details',
        'events',
        'articles',
        'documents',
        'equipment',
        'votes',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
