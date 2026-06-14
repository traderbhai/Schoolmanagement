<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_work_items', function (Blueprint $table) {
            $table->id();
            $table->string('work_type', 100)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 40)->default('normal')->index();
            $table->string('status', 60)->default('open')->index();
            $table->string('severity', 40)->default('normal')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->json('metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['work_type', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index(['program_id', 'work_type']);
        });

        Schema::create('academic_pmc_curriculum_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 60)->default('draft')->index();
            $table->string('approval_status', 60)->default('pmc_review')->index();
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->json('credit_rules')->nullable();
            $table->json('obe_requirements')->nullable();
            $table->json('compliance_rules')->nullable();
            $table->timestamp('rollout_due_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_faculty_load_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('planned_hours')->default(0);
            $table->unsignedInteger('allocated_hours')->default(0);
            $table->unsignedInteger('mentoring_load')->default(0);
            $table->unsignedInteger('exam_load')->default(0);
            $table->string('load_band', 40)->default('balanced')->index();
            $table->string('status', 60)->default('draft')->index();
            $table->boolean('adjunct_required')->default(false)->index();
            $table->json('constraints')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_controls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('title');
            $table->string('status', 60)->default('draft')->index();
            $table->unsignedInteger('draft_slots')->default(0);
            $table->unsignedInteger('published_slots')->default(0);
            $table->unsignedInteger('teacher_conflicts')->default(0);
            $table->unsignedInteger('room_conflicts')->default(0);
            $table->timestamp('freeze_due_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_student_success_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('mentor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('risk_type', 80)->index();
            $table->string('risk_band', 40)->default('medium')->index();
            $table->string('status', 60)->default('open')->index();
            $table->text('intervention_plan')->nullable();
            $table->timestamp('next_review_at')->nullable()->index();
            $table->boolean('parent_escalation_required')->default(false)->index();
            $table->json('signals')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_review_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('review_type', 80)->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 60)->default('scheduled')->index();
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('surface', 80)->index();
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('academic_pmc_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_key', 100)->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('exported_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_export_logs');
        Schema::dropIfExists('academic_pmc_saved_views');
        Schema::dropIfExists('academic_pmc_review_meetings');
        Schema::dropIfExists('academic_pmc_student_success_plans');
        Schema::dropIfExists('academic_pmc_timetable_controls');
        Schema::dropIfExists('academic_pmc_faculty_load_plans');
        Schema::dropIfExists('academic_pmc_curriculum_plans');
        Schema::dropIfExists('academic_pmc_work_items');
    }
};
