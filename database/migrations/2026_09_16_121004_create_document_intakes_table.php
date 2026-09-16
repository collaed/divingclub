<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_intakes', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_path');
            // Type this was routed to, and how sure the classifier was — a
            // bureau member reviewing the list needs to see the assumption,
            // not just trust it silently.
            $table->string('detected_type')->nullable(); // 'licence_scan' | 'bank_statement'
            $table->string('detection_reason')->nullable();
            $table->foreignId('federation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('processing'); // processing | routed | needs_review | failed
            $table->string('error')->nullable();
            $table->foreignId('licence_scan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('transactions_created')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_intakes');
    }
};
