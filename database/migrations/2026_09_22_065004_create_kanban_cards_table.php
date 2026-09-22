<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Action items extracted from compte-rendu documents. On the environment
 * that only sources the documents (production), the same table exists but
 * stays empty — extraction there pushes to the board environment instead of
 * writing locally. See config/kanban.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('responsible')->nullable();
            $table->text('context')->nullable();
            $table->string('status')->default('todo'); // todo | doing | done
            $table->string('source_document_name');
            $table->string('source_document_folder')->nullable();
            $table->date('source_document_date')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_cards');
    }
};
