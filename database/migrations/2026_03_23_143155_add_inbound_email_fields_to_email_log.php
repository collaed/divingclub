<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            $table->string('direction', 10)->default('outbound')->after('status'); // inbound, outbound, contact
            $table->string('alias')->nullable()->after('to_email'); // original alias (bureau@, event-5@, etc.)
            $table->boolean('authorized')->default(true)->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('email_log', function (Blueprint $table) {
            $table->dropColumn(['direction', 'alias', 'authorized']);
        });
    }
};
