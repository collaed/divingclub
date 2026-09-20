<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Members younger than this at the licence anchor pay
            // `minor_fee_percent` of the nominal club cotisation.
            $table->unsignedTinyInteger('minor_fee_below_age')->default(18);
            $table->unsignedTinyInteger('minor_fee_percent')->default(50);
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['minor_fee_below_age', 'minor_fee_percent']);
        });
    }
};
