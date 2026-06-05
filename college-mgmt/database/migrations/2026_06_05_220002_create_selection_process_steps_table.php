<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_process_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['gd', 'pi', 'wat', 'written_test', 'aptitude', 'presentation'])->default('pi');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->decimal('weightage', 5, 2)->default(100.00);
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['program_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selection_process_steps');
    }
};
