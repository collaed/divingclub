<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_dispatches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('library_file_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('subject');
            $t->text('message')->nullable();
            $t->string('recipient_summary')->nullable();
            $t->timestamps();
        });

        Schema::create('document_dispatch_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('dispatch_id')->constrained('document_dispatches')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('email');
            $t->string('token', 48)->unique();
            $t->timestamp('sent_at')->nullable();
            $t->string('send_error')->nullable();
            $t->timestamp('first_opened_at')->nullable();
            $t->unsignedInteger('opens_count')->default(0);
            $t->timestamps();
        });

        Schema::create('document_dispatch_opens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('recipient_id')->constrained('document_dispatch_recipients')->cascadeOnDelete();
            $t->string('ip_address', 45)->nullable();
            $t->char('country_code', 2)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('opened_at')->index();
            // Append-only, preserved — no updated_at, never pruned.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_dispatch_opens');
        Schema::dropIfExists('document_dispatch_recipients');
        Schema::dropIfExists('document_dispatches');
    }
};
