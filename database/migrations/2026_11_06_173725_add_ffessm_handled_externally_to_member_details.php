<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A handful of members hold their FFESSM licence directly with the
 * federation rather than through the club — paying their club cotisation
 * must not flag them as needing the club to renew a FFESSM licence it
 * never handles for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_details', function (Blueprint $table): void {
            $table->boolean('ffessm_handled_externally')->default(false)->after('active_instructor');
        });
    }

    public function down(): void
    {
        Schema::table('member_details', function (Blueprint $table): void {
            $table->dropColumn('ffessm_handled_externally');
        });
    }
};
