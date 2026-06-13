<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Course Outcomes (CO) — defined per subject
        Schema::create('course_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);       // e.g. CO1, CO2
            $table->string('description');    // "Understand and apply sorting algorithms"
            $table->enum('bloom_level', ['remember','understand','apply','analyze','evaluate','create'])->default('understand');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['subject_id', 'code']);
        });

        // Program Outcomes (PO) — defined at program level
        Schema::create('program_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);       // PO1, PO2 … PO12 (standard NBA)
            $table->string('description');
            $table->enum('category', ['engineering','management','science','commerce','general'])->default('general');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['program_id', 'code']);
        });

        // Program Specific Outcomes (PSO) — optional, used by NBA
        Schema::create('program_specific_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);       // PSO1, PSO2
            $table->string('description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['program_id', 'code']);
        });

        // CO-PO Mapping matrix (correlation level 1-3 or 0 for none)
        Schema::create('co_po_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_outcome_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_outcome_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_specific_outcome_id')->nullable()->constrained('program_specific_outcomes')->nullOnDelete();
            $table->tinyInteger('correlation_level')->default(0); // 0=none, 1=low, 2=medium, 3=high
            $table->timestamps();
        });

        // CO Attainment — direct (from exam marks) + indirect (from survey)
        Schema::create('co_attainments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_outcome_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('direct_attainment', 5, 2)->default(0);    // from exam scores
            $table->decimal('indirect_attainment', 5, 2)->default(0);  // from surveys
            $table->decimal('final_attainment', 5, 2)->default(0);     // weighted average
            $table->decimal('target_attainment', 5, 2)->default(60);   // threshold (e.g. 60%)
            $table->boolean('target_met')->default(false);
            $table->integer('students_assessed')->default(0);
            $table->integer('students_attained')->default(0);           // students >= threshold
            $table->timestamps();
            $table->unique(['course_outcome_id', 'term_id', 'batch_id']);
        });

        // PO Attainment — aggregated from CO attainments
        Schema::create('po_attainments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_outcome_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->decimal('attainment_value', 5, 2)->default(0);
            $table->decimal('target_value', 5, 2)->default(60);
            $table->boolean('target_met')->default(false);
            $table->timestamps();
            $table->unique(['program_outcome_id', 'program_id', 'term_id']);
        });

        // Indirect assessment (student exit survey / feedback for OBE)
        Schema::create('obe_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_published')->default(false);
            $table->date('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('obe_survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obe_survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_outcome_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->default(3); // 1-5 Likert scale
            $table->timestamps();
            $table->unique(['obe_survey_id', 'student_id', 'course_outcome_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obe_survey_responses');
        Schema::dropIfExists('obe_surveys');
        Schema::dropIfExists('po_attainments');
        Schema::dropIfExists('co_attainments');
        Schema::dropIfExists('co_po_mappings');
        Schema::dropIfExists('program_specific_outcomes');
        Schema::dropIfExists('program_outcomes');
        Schema::dropIfExists('course_outcomes');
    }
};
