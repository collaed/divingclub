<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('slot')->default('evening'); // morning, afternoon, evening, full_day
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_availabilities');
    }
};
