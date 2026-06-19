<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmc_assessment_component_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_subject_id')->nullable()->constrained('program_subjects')->nullOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('max_marks', 8, 2);
            $table->decimal('weightage', 5, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['subject_id', 'term_id', 'name'], 'pmc_assessment_subject_term_name_unique');
            $table->index(['program_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmc_assessment_component_configs');
    }
};
