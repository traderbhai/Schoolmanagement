<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_assessment_rubrics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('assessment_type')->index();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('minimum_score', 8, 2)->default(0);
            $table->json('recommendation_options')->nullable();
            $table->text('evaluator_instructions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_assessment_rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained('admission_assessment_rubrics')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2);
            $table->decimal('weight', 8, 2)->default(1);
            $table->boolean('requires_comment')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('admission_evaluator_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('admission_assessment_panel_assignments')->cascadeOnDelete();
            $table->foreignId('rubric_id')->nullable()->constrained('admission_assessment_rubrics')->nullOnDelete();
            $table->foreignId('criterion_id')->nullable()->constrained('admission_assessment_rubric_criteria')->nullOnDelete();
            $table->foreignId('evaluator_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('weighted_score', 8, 2)->default(0);
            $table->text('comment')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('admission_assessment_panel_assignments')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->index();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->nullable()->constrained('admission_assessment_panel_assignments')->nullOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->foreignId('to_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->timestamp('old_scheduled_at')->nullable();
            $table->timestamp('new_scheduled_at')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('requested')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('artifact_type')->index();
            $table->string('title');
            $table->string('topic')->nullable();
            $table->string('group_number')->nullable();
            $table->string('artifact_url')->nullable();
            $table->unsignedInteger('prep_minutes')->nullable();
            $table->timestamp('submission_due_at')->nullable();
            $table->text('moderator_notes')->nullable();
            $table->text('observer_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_counsellor_playbooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('playbook_type')->index();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_counsellor_playbook_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playbook_id')->constrained('admission_counsellor_playbooks')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('suggested_action')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('admission_counselling_profiles', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('preferred_program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->string('budget_sensitivity')->nullable();
            $table->boolean('scholarship_need')->default(false);
            $table->boolean('hostel_interest')->default(false);
            $table->boolean('transport_interest')->default(false);
            $table->string('parent_decision_maker')->nullable();
            $table->string('key_objection')->nullable()->index();
            $table->string('lost_reason')->nullable()->index();
            $table->string('competitor_considered')->nullable();
            $table->boolean('parent_spoken')->default(false);
            $table->timestamp('last_parent_contacted_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_conversation_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('event_type')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('admission_assessment_panels', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_assessment_panels', 'rubric_id')) {
                $table->foreignId('rubric_id')->nullable()->after('selection_session_id')->constrained('admission_assessment_rubrics')->nullOnDelete();
            }
            if (!Schema::hasColumn('admission_assessment_panels', 'readiness_status')) {
                $table->string('readiness_status')->default('needs_setup')->after('status')->index();
            }
        });

        Schema::table('admission_assessment_panel_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_assessment_panel_assignments', 'lifecycle_status')) {
                $table->string('lifecycle_status')->default('invited')->after('attendance_status')->index();
            }
            if (!Schema::hasColumn('admission_assessment_panel_assignments', 'aggregate_score')) {
                $table->decimal('aggregate_score', 8, 2)->nullable()->after('recommendation');
            }
            if (!Schema::hasColumn('admission_assessment_panel_assignments', 'variance_score')) {
                $table->decimal('variance_score', 8, 2)->nullable()->after('aggregate_score');
            }
            if (!Schema::hasColumn('admission_assessment_panel_assignments', 'variance_flag')) {
                $table->boolean('variance_flag')->default(false)->after('variance_score')->index();
            }
        });

        Schema::table('session_applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('session_applicants', 'lifecycle_status')) {
                $table->string('lifecycle_status')->default('invited')->after('attendance_status')->index();
            }
            if (!Schema::hasColumn('session_applicants', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('lifecycle_status');
            }
            if (!Schema::hasColumn('session_applicants', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('checked_in_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_conversation_events');
        Schema::dropIfExists('admission_counselling_profiles');
        Schema::dropIfExists('admission_counsellor_playbook_steps');
        Schema::dropIfExists('admission_counsellor_playbooks');
        Schema::dropIfExists('admission_assessment_artifacts');
        Schema::dropIfExists('admission_assessment_reschedules');
        Schema::dropIfExists('admission_assessment_lifecycle_events');
        Schema::dropIfExists('admission_evaluator_scores');
        Schema::dropIfExists('admission_assessment_rubric_criteria');
        Schema::dropIfExists('admission_assessment_rubrics');
    }
};
