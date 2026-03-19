<?php

/**
 * Replace binary is_public with role-based visibility on library_files.
 *
 * @author ClubCEP.eu
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            // public=anyone, members=logged-in, instructors=instructor+bureau, bureau=bureau only
            $table->string('visibility', 20)->default('members')->after('is_public');
        });

        // Migrate existing data
        DB::table('library_files')->where('is_public', true)->update(['visibility' => 'public']);
        DB::table('library_files')->where('is_public', false)->update(['visibility' => 'bureau']);

        Schema::table('library_files', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }

    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('folder');
        });

        DB::table('library_files')->where('visibility', 'public')->update(['is_public' => true]);

        Schema::table('library_files', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
