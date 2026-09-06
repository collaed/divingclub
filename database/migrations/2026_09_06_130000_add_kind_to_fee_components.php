<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a `kind` discriminator to membership_fee_components so the dues calculator
 * can tell FFESSM-licence rows, the FLASSA licence row, and assurance rows apart
 * without hard-coding slugs in domain code. Values:
 * ffessm_licence | flassa | assurance | other (default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_fee_components', function (Blueprint $table): void {
            if (! Schema::hasColumn('membership_fee_components', 'kind')) {
                $table->string('kind')->default('other')->after('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_fee_components', function (Blueprint $table): void {
            if (Schema::hasColumn('membership_fee_components', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
