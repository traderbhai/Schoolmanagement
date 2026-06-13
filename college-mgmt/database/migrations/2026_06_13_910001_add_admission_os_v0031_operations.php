<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_cadence_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('target_type')->default('lead');
            $table->string('reason')->default('follow_up');
            $table->string('channel')->default('email');
            $table->foreignId('template_id')->nullable()->constrained('admission_communication_templates')->nullOnDelete();
            $table->json('repeat_rule')->nullable();
            $table->unsignedInteger('max_attempts')->default(3);
            $table->unsignedInteger('escalate_after_attempts')->default(2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_reminder_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('cadence_rule_id')->nullable()->constrained('admission_cadence_rules')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('admission_communication_templates')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target')->default('lead');
            $table->string('reason')->default('follow_up');
            $table->string('channel')->default('email');
            $table->string('status')->default('scheduled');
            $table->string('priority')->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->json('repeat_rule')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('admission_assessment_panels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('panel_type')->default('personal_interview');
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('selection_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->unsignedInteger('capacity')->default(20);
            $table->string('venue')->nullable();
            $table->string('online_link')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_panel_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('evaluator');
            $table->boolean('is_chair')->default(false);
            $table->timestamps();

            $table->unique(['panel_id', 'user_id']);
        });

        Schema::create('admission_assessment_panel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->foreignId('selection_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attendance_status')->default('pending');
            $table->string('score_status')->default('pending');
            $table->string('recommendation')->nullable();
            $table->text('manager_override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('score_locked_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['panel_id', 'applicant_id']);
        });

        Schema::create('admission_walk_ins', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_name');
            $table->string('visitor_phone')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose')->default('admission_enquiry');
            $table->foreignId('assigned_counsellor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('outcome')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_manager_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reviewable_type');
            $table->unsignedBigInteger('reviewable_id');
            $table->string('review_type');
            $table->string('status')->default('pending');
            $table->string('severity')->default('normal');
            $table->foreignId('assigned_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('finding')->nullable();
            $table->text('action_required')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index(['status', 'due_at']);
        });

        Schema::table('applicant_scores', function (Blueprint $table) {
            if (!Schema::hasColumn('applicant_scores', 'score_status')) {
                $table->string('score_status')->default('draft');
            }
            if (!Schema::hasColumn('applicant_scores', 'locked_at')) {
                $table->timestamp('locked_at')->nullable();
            }
            if (!Schema::hasColumn('applicant_scores', 'locked_by')) {
                $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('applicant_scores', 'override_reason')) {
                $table->text('override_reason')->nullable();
            }
            if (!Schema::hasColumn('applicant_scores', 'recommendation')) {
                $table->string('recommendation')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_manager_reviews');
        Schema::dropIfExists('admission_walk_ins');
        Schema::dropIfExists('admission_assessment_panel_assignments');
        Schema::dropIfExists('admission_assessment_panel_members');
        Schema::dropIfExists('admission_assessment_panels');
        Schema::dropIfExists('admission_reminder_schedules');
        Schema::dropIfExists('admission_cadence_rules');
    }
};
