<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document library (FileGator equivalent)
        Schema::create('library_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('folder')->default('/');
            $table->boolean('is_public')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('folder');
            $table->index('is_public');
        });

        // Instructor bio fields
        Schema::table('member_details', function (Blueprint $table) {
            $table->text('instructor_bio')->nullable()->after('active_instructor');
            $table->text('instructor_specialties')->nullable()->after('instructor_bio');
            $table->text('instructor_motivation')->nullable()->after('instructor_specialties');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_files');
        Schema::table('member_details', function (Blueprint $table) {
            $table->dropColumn(['instructor_bio', 'instructor_specialties', 'instructor_motivation']);
        });
    }
};
