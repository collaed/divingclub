<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_emails', function (Blueprint $table) {
            $table->boolean('receive_mail')->default(true)->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('user_emails', fn (Blueprint $t) => $t->dropColumn('receive_mail'));
    }
};
