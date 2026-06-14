<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_course_allocation_batches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('draft')->index();
            $table->unsignedInteger('student_count')->default(0);
            $table->unsignedInteger('core_allocations')->default(0);
            $table->unsignedInteger('elective_allocations')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->json('rules')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_student_course_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_batch_id')->nullable()->constrained('academic_pmc_course_allocation_batches')->nullOnDelete();
            $table->foreignId('student_subject_enrollment_id')->nullable()->constrained('student_subject_enrollments')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('allocation_type', 80)->default('core')->index();
            $table->string('allocation_source', 100)->default('pmc')->index();
            $table->string('approval_status', 80)->default('allocated')->index();
            $table->string('basket_status', 80)->default('allocated')->index();
            $table->unsignedInteger('priority_rank')->nullable();
            $table->boolean('waitlisted')->default(false)->index();
            $table->text('override_reason')->nullable();
            $table->json('validation_flags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'term_id']);
            $table->index(['subject_id', 'term_id']);
        });

        Schema::create('academic_pmc_course_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('group_type', 80)->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('min_capacity')->default(1);
            $table->unsignedInteger('max_capacity')->default(60);
            $table->unsignedInteger('current_strength')->default(0);
            $table->string('status', 80)->default('draft')->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->json('constraints')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['group_type', 'status']);
            $table->index(['program_id', 'term_id']);
        });

        Schema::create('academic_pmc_course_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained('academic_pmc_course_groups')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('student_course_allocation_id')->nullable()->constrained('academic_pmc_student_course_allocations')->nullOnDelete();
            $table->string('status', 80)->default('active')->index();
            $table->text('move_reason')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['course_group_id', 'student_id']);
        });

        Schema::create('academic_pmc_group_faculty_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained('academic_pmc_course_groups')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('assignment_role', 80)->default('primary')->index();
            $table->string('assignment_source', 80)->default('pmc')->index();
            $table->string('approval_status', 80)->default('pmc_approved')->index();
            $table->unsignedInteger('weekly_hours')->default(0);
            $table->boolean('is_backup')->default(false)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['course_group_id', 'teacher_id', 'assignment_role'], 'pmc_group_faculty_unique');
        });

        Schema::create('academic_pmc_faculty_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('faculty_type', 80)->default('regular')->index();
            $table->json('available_days')->nullable();
            $table->json('preferred_slots')->nullable();
            $table->json('unavailable_slots')->nullable();
            $table->unsignedInteger('max_classes_per_day')->default(4);
            $table->unsignedInteger('max_consecutive_classes')->default(3);
            $table->unsignedInteger('max_weekly_load')->default(18);
            $table->json('subject_expertise')->nullable();
            $table->text('restriction_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'term_id']);
        });

        Schema::create('academic_pmc_workload_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedInteger('normal_weekly_hours')->default(16);
            $table->unsignedInteger('overload_threshold')->default(18);
            $table->unsignedInteger('underload_threshold')->default(8);
            $table->unsignedInteger('max_subjects')->default(4);
            $table->unsignedInteger('mentor_capacity')->default(30);
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('academic_pmc_locked_slots', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slot_type', 80)->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('course_group_id')->nullable()->constrained('academic_pmc_course_groups')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('timetable_slot_id')->constrained('timetable_slots')->cascadeOnDelete();
            $table->boolean('is_hard_lock')->default(true)->index();
            $table->string('status', 80)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_generation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('strategy', 80)->default('balanced')->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('timetable_version_id')->nullable()->constrained('timetable_versions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('generated')->index();
            $table->unsignedInteger('scheduled_count')->default(0);
            $table->unsignedInteger('unscheduled_count')->default(0);
            $table->unsignedInteger('hard_conflict_count')->default(0);
            $table->unsignedInteger('soft_warning_count')->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->json('input_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_generation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_run_id')->constrained('academic_pmc_timetable_generation_runs')->cascadeOnDelete();
            $table->foreignId('course_group_id')->nullable()->constrained('academic_pmc_course_groups')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->foreignId('timetable_slot_id')->nullable()->constrained('timetable_slots')->nullOnDelete();
            $table->string('status', 80)->default('scheduled')->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->unsignedInteger('confidence')->default(0);
            $table->text('explanation')->nullable();
            $table->json('conflicts')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_quality_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->cascadeOnDelete();
            $table->foreignId('timetable_version_id')->nullable()->constrained('timetable_versions')->nullOnDelete();
            $table->unsignedTinyInteger('overall_score')->default(0);
            $table->unsignedInteger('hard_conflicts')->default(0);
            $table->unsignedInteger('soft_warnings')->default(0);
            $table->unsignedTinyInteger('student_compactness_score')->default(0);
            $table->unsignedTinyInteger('faculty_balance_score')->default(0);
            $table->unsignedTinyInteger('room_utilization_score')->default(0);
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_constraints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->nullOnDelete();
            $table->string('constraint_type', 100)->index();
            $table->string('severity', 40)->default('hard')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('affected_type', 100)->nullable();
            $table->string('affected_key')->nullable();
            $table->text('recommended_fix')->nullable();
            $table->string('source_route')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_version_id')->nullable()->constrained('timetable_versions')->nullOnDelete();
            $table->string('change_type', 80)->index();
            $table->string('status', 80)->default('requested')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('impact_summary')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_impact_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('change_request_id')->nullable()->constrained('academic_pmc_timetable_change_requests')->cascadeOnDelete();
            $table->string('impact_type', 100)->index();
            $table->string('title');
            $table->unsignedInteger('affected_count')->default(0);
            $table->json('affected_records')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_substitution_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_entry_id')->nullable()->constrained('timetable_entries')->nullOnDelete();
            $table->foreignId('course_group_id')->nullable()->constrained('academic_pmc_course_groups')->nullOnDelete();
            $table->foreignId('original_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->date('substitution_date')->nullable()->index();
            $table->string('status', 80)->default('recommended')->index();
            $table->unsignedInteger('score')->default(0);
            $table->json('reasons')->nullable();
            $table->json('conflict_checks')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_timetable_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notification_type', 100)->index();
            $table->string('recipient_type', 80)->index();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('status', 80)->default('queued')->index();
            $table->string('source_type', 100)->nullable();
            $table->string('source_key')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_timetable_notifications');
        Schema::dropIfExists('academic_pmc_substitution_recommendations');
        Schema::dropIfExists('academic_pmc_timetable_impact_records');
        Schema::dropIfExists('academic_pmc_timetable_change_requests');
        Schema::dropIfExists('academic_pmc_timetable_constraints');
        Schema::dropIfExists('academic_pmc_timetable_quality_scores');
        Schema::dropIfExists('academic_pmc_timetable_generation_items');
        Schema::dropIfExists('academic_pmc_timetable_generation_runs');
        Schema::dropIfExists('academic_pmc_locked_slots');
        Schema::dropIfExists('academic_pmc_workload_rules');
        Schema::dropIfExists('academic_pmc_faculty_preferences');
        Schema::dropIfExists('academic_pmc_group_faculty_assignments');
        Schema::dropIfExists('academic_pmc_course_group_members');
        Schema::dropIfExists('academic_pmc_course_groups');
        Schema::dropIfExists('academic_pmc_student_course_allocations');
        Schema::dropIfExists('academic_pmc_course_allocation_batches');
    }
};
