<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diving_prerogatives', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique(); // PE-12, PE-20, PA-20, PA-40, etc.
            $table->string('name');               // "Plongeur Encadré 20m"
            $table->enum('type', ['supervised', 'autonomous', 'guide', 'teach']);
            $table->unsignedSmallInteger('max_depth');
            $table->boolean('requires_adult')->default(false);
            $table->timestamps();
        });

        Schema::create('certification_prerogatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('certification_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diving_prerogative_id')->constrained()->cascadeOnDelete();
            $table->unique(['certification_level_id', 'diving_prerogative_id'], 'cert_prerog_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_prerogatives');
        Schema::dropIfExists('diving_prerogatives');
    }
};
