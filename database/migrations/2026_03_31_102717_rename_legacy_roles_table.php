<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('roles', 'legacy_roles');
    }

    public function down(): void
    {
        Schema::rename('legacy_roles', 'roles');
    }
};
