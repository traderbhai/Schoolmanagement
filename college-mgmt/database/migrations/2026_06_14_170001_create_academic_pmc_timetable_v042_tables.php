<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_elective_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedInteger('preference_rank')->default(1);
            $table->unsignedInteger('priority_score')->default(0);
            $table->string('status', 80)->default('submitted')->index();
            $table->string('choice_source', 100)->default('student_choice')->index();
            $table->text('decision_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'term_id', 'subject_id'], 'pmc_elective_choice_unique');
            $table->index(['program_id', 'batch_id', 'term_id']);
            $table->index(['subject_id', 'term_id', 'status']);
        });

        Schema::create('academic_pmc_group_build_runs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('group_type', 80)->default('core_section')->index();
            $table->string('strategy', 80)->default('balanced_capacity')->index();
            $table->unsignedInteger('min_capacity')->default(1);
            $table->unsignedInteger('max_capacity')->default(60);
            $table->unsignedInteger('students_considered')->default(0);
            $table->unsignedInteger('groups_created')->default(0);
            $table->unsignedInteger('warnings_count')->default(0);
            $table->string('status', 80)->default('completed')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('warnings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'term_id', 'group_type']);
        });

        Schema::create('academic_pmc_timetable_solver_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_run_id')->constrained('academic_pmc_timetable_generation_runs')->cascadeOnDelete();
            $table->string('strategy', 80)->default('balanced')->index();
            $table->string('status', 80)->default('completed')->index();
            $table->unsignedInteger('placements_attempted')->default(0);
            $table->unsignedInteger('placements_scheduled')->default(0);
            $table->unsignedInteger('placements_unscheduled')->default(0);
            $table->unsignedInteger('hard_conflicts')->default(0);
            $table->unsignedInteger('soft_warnings')->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->json('diagnostics')->nullable();
            $table->timestamps();

            $table->index(['generation_run_id', 'status']);
        });

        Schema::create('academic_pmc_timetable_publish_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->cascadeOnDelete();
            $table->foreignId('timetable_version_id')->nullable()->constrained('timetable_versions')->nullOnDelete();
            $table->string('check_type', 100)->index();
            $table->string('status', 40)->default('pass')->index();
            $table->string('severity', 40)->default('info')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('required_role', 100)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['generation_run_id', 'status']);
            $table->index(['check_type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_timetable_publish_checks');
        Schema::dropIfExists('academic_pmc_timetable_solver_attempts');
        Schema::dropIfExists('academic_pmc_group_build_runs');
        Schema::dropIfExists('academic_pmc_elective_choices');
    }
};
