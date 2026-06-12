<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table): void {
            $table->string('tshirt_size', 10)->nullable()->after('bcd_notes');
            $table->string('suit_brand')->nullable()->after('tshirt_size');
            $table->string('suit_size', 20)->nullable()->after('suit_brand');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table): void {
            $table->dropColumn(['tshirt_size', 'suit_brand', 'suit_size']);
        });
    }
};
