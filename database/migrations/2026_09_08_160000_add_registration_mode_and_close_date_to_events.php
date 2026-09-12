<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // open = normal, required = mandatory, not_needed = just show up
            $table->string('registration_mode', 20)->default('open');
            $table->timestamp('inscription_close_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['registration_mode', 'inscription_close_at']);
        });
    }
};
