<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_call_attempts', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('caller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('disposition', 40)->index();
            $table->string('outcome', 80)->nullable()->index();
            $table->timestamp('attempted_at')->nullable()->index();
            $table->timestamp('retry_due_at')->nullable()->index();
            $table->boolean('final_attempt')->default(false)->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_call_queue_skips', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 120);
            $table->timestamp('skipped_until')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('resource_type', 40)->default('room')->index();
            $table->unsignedInteger('capacity')->default(1);
            $table->string('location')->nullable();
            $table->string('online_link')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_session_id')->nullable()->constrained('selection_sessions')->nullOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('admission_assessment_resources')->nullOnDelete();
            $table->string('slot_code')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->unsignedInteger('capacity')->default(1);
            $table->string('venue')->nullable();
            $table->string('online_link')->nullable();
            $table->string('status', 40)->default('open')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_slot_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('admission_assessment_slots')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('panel_assignment_id')->nullable()->constrained('admission_assessment_panel_assignments')->nullOnDelete();
            $table->string('status', 40)->default('assigned')->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['slot_id', 'applicant_id']);
        });

        Schema::create('admission_assessment_reschedule_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_assignment_id')->nullable()->constrained('admission_assessment_slot_assignments')->nullOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_slot_id')->nullable()->constrained('admission_assessment_slots')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_resource_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('admission_assessment_resources')->cascadeOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('admission_assessment_slots')->nullOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->string('status', 40)->default('booked')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_evaluator_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamps();
            $table->unique(['panel_id', 'user_id']);
        });

        Schema::create('admission_gd_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('admission_assessment_slots')->nullOnDelete();
            $table->unsignedInteger('group_number')->default(1);
            $table->string('topic')->nullable();
            $table->foreignId('moderator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('observer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('capacity')->default(8);
            $table->string('status', 40)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_gd_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gd_group_id')->constrained('admission_gd_groups')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('lifecycle_status', 40)->default('invited')->index();
            $table->timestamps();
            $table->unique(['gd_group_id', 'applicant_id']);
        });

        Schema::create('admission_assessment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('admission_assessment_slots')->nullOnDelete();
            $table->string('submission_type', 60)->index();
            $table->string('artifact_url')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('originality_flag')->default(false)->index();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_selection_committee_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('panel_id')->nullable()->constrained('admission_assessment_panels')->nullOnDelete();
            $table->string('decision', 40)->index();
            $table->text('reason');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->index();
            $table->decimal('normalized_score', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_offer_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('round_number')->default(1);
            $table->string('name');
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('offer_valid_until')->nullable()->index();
            $table->string('status', 40)->default('draft')->index();
            $table->string('source_type', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_round_id')->nullable()->constrained('admission_offer_rounds')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rank')->default(1)->index();
            $table->string('category', 60)->nullable()->index();
            $table->string('status', 40)->default('waiting')->index();
            $table->timestamp('promoted_at')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('promotion_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_round_id')->nullable()->constrained('admission_offer_rounds')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 40)->default('held')->index();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->foreignId('extended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('extension_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_seat_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 60)->index();
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_deferrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('to_batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->text('reason');
            $table->string('status', 40)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('carry_forward_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_joining_kit_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('task_key', 80)->index();
            $table->string('title');
            $table->string('status', 40)->default('pending')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['applicant_id', 'task_key']);
        });

        Schema::create('admission_consent_records', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('channel', 40)->index();
            $table->string('status', 40)->default('opt_in')->index();
            $table->string('source', 80)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('consented_at')->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id', 'channel']);
        });

        Schema::create('admission_consent_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_record_id')->constrained('admission_consent_records')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_quiet_hour_rules', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 40)->index();
            $table->time('starts_at_time');
            $table->time('ends_at_time');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('emergency_override_allowed')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_template_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('admission_communication_templates')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'version']);
        });

        Schema::create('admission_bulk_send_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('admission_communication_templates')->nullOnDelete();
            $table->string('channel', 40)->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('audience_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->string('status', 40)->default('preview')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_sensitive_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100)->index();
            $table->nullableMorphs('subject');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('route_name')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_integration_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained('admission_integration_providers')->nullOnDelete();
            $table->string('status', 40)->default('sandbox_only')->index();
            $table->boolean('credentials_present')->default(false);
            $table->boolean('base_url_reachable')->default(false);
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_failure_reason')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_integration_retry_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_log_id')->nullable()->constrained('admission_communication_logs')->nullOnDelete();
            $table->string('provider_name', 80)->index();
            $table->string('channel', 40)->index();
            $table->string('failure_type', 80)->nullable()->index();
            $table->boolean('retryable')->default(true)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->string('status', 40)->default('queued')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_quick_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('query');
            $table->string('result_type', 80)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_quick_search_logs');
        Schema::dropIfExists('admission_integration_retry_queue');
        Schema::dropIfExists('admission_integration_health_checks');
        Schema::dropIfExists('admission_sensitive_audit_events');
        Schema::dropIfExists('admission_bulk_send_previews');
        Schema::dropIfExists('admission_template_approvals');
        Schema::dropIfExists('admission_quiet_hour_rules');
        Schema::dropIfExists('admission_consent_histories');
        Schema::dropIfExists('admission_consent_records');
        Schema::dropIfExists('admission_joining_kit_tasks');
        Schema::dropIfExists('admission_deferrals');
        Schema::dropIfExists('admission_seat_movements');
        Schema::dropIfExists('admission_seat_holds');
        Schema::dropIfExists('admission_waitlist_entries');
        Schema::dropIfExists('admission_offer_rounds');
        Schema::dropIfExists('admission_selection_committee_decisions');
        Schema::dropIfExists('admission_assessment_submissions');
        Schema::dropIfExists('admission_gd_group_members');
        Schema::dropIfExists('admission_gd_groups');
        Schema::dropIfExists('admission_evaluator_invitations');
        Schema::dropIfExists('admission_assessment_resource_bookings');
        Schema::dropIfExists('admission_assessment_reschedule_requests');
        Schema::dropIfExists('admission_assessment_slot_assignments');
        Schema::dropIfExists('admission_assessment_slots');
        Schema::dropIfExists('admission_assessment_resources');
        Schema::dropIfExists('admission_call_queue_skips');
        Schema::dropIfExists('admission_call_attempts');
    }
};
