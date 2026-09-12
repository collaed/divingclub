<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('image_path')->nullable();
            $table->string('extracted_name')->nullable();
            $table->string('extracted_number')->nullable();
            $table->string('extracted_year', 20)->nullable();
            $table->foreignId('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['processing', 'applied', 'needs_review', 'failed', 'discarded'])->default('processing');
            $table->text('extraction_error')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('member_licences', function (Blueprint $table) {
            $table->string('scan_image_path')->nullable()->after('registration_date');
        });
    }

    public function down(): void
    {
        Schema::table('member_licences', function (Blueprint $table) {
            $table->dropColumn('scan_image_path');
        });
        Schema::dropIfExists('licence_scans');
    }
};
