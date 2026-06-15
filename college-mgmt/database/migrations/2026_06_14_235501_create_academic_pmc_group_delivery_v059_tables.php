<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_pmc_group_delivery_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained('academic_pmc_course_groups')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('planned_sessions')->default(0);
            $table->unsignedInteger('conducted_sessions')->default(0);
            $table->unsignedInteger('missed_sessions')->default(0);
            $table->unsignedInteger('rescheduled_sessions')->default(0);
            $table->unsignedInteger('cancelled_sessions')->default(0);
            $table->unsignedInteger('pending_session_logs')->default(0);
            $table->decimal('attendance_percent', 5, 2)->nullable();
            $table->unsignedTinyInteger('delivery_progress')->default(0);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_band', 40)->default('low')->index();
            $table->string('status', 80)->default('monitoring')->index();
            $table->timestamp('next_review_at')->nullable()->index();
            $table->json('risk_reasons')->nullable();
            $table->json('recommended_actions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('course_group_id', 'pmc_group_delivery_unique');
            $table->index(['program_id', 'risk_band'], 'pmc_group_delivery_program_risk_idx');
            $table->index(['teacher_id', 'status'], 'pmc_group_delivery_teacher_status_idx');
            $table->index(['owner_user_id', 'status'], 'pmc_group_delivery_owner_status_idx');
        });

        Schema::create('academic_pmc_session_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_delivery_tracker_id')->nullable()->constrained('academic_pmc_group_delivery_trackers')->nullOnDelete();
            $table->foreignId('generation_item_id')->nullable()->constrained('academic_pmc_timetable_generation_items')->nullOnDelete();
            $table->foreignId('course_group_id')->nullable()->constrained('academic_pmc_course_groups')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->foreignId('timetable_slot_id')->nullable()->constrained('timetable_slots')->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable()->index();
            $table->date('scheduled_date')->nullable()->index();
            $table->string('session_status', 80)->default('planned')->index();
            $table->string('delivery_type', 80)->default('lecture')->index();
            $table->boolean('attendance_marked')->default(false)->index();
            $table->boolean('lesson_plan_updated')->default(false)->index();
            $table->boolean('material_uploaded')->default(false)->index();
            $table->text('topic_planned')->nullable();
            $table->text('topic_covered')->nullable();
            $table->text('gap_reason')->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['generation_item_id', 'scheduled_date'], 'pmc_session_delivery_item_date_unique');
            $table->index(['course_group_id', 'session_status'], 'pmc_session_delivery_group_status_idx');
            $table->index(['teacher_id', 'session_status'], 'pmc_session_delivery_teacher_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_session_delivery_logs');
        Schema::dropIfExists('academic_pmc_group_delivery_trackers');
    }
};
