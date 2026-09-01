<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('alias')->unique();
            $table->string('type')->default('member'); // member | sas_static | sas_conv
            $table->boolean('active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_aliases');
    }
};
