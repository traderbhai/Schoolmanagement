<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_communication_logs', function (Blueprint $table) {
            foreach ([
                'provider_message_id' => fn () => $table->string('provider_message_id')->nullable()->after('provider')->index(),
                'provider_request_id' => fn () => $table->string('provider_request_id')->nullable()->after('provider_message_id'),
                'delivery_state' => fn () => $table->string('delivery_state', 40)->nullable()->after('status')->index(),
                'retry_count' => fn () => $table->unsignedInteger('retry_count')->default(0)->after('failure_reason'),
                'last_synced_at' => fn () => $table->timestamp('last_synced_at')->nullable()->after('retry_count'),
                'webhook_payload' => fn () => $table->json('webhook_payload')->nullable()->after('last_synced_at'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('admission_communication_logs', $column)) {
                    $definition();
                }
            }
        });

        Schema::create('admission_integration_providers', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 40)->index();
            $table->string('provider_name', 80);
            $table->string('base_url')->nullable();
            $table->json('credential_keys')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('sandbox_mode')->default(true)->index();
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->json('retry_policy')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['channel', 'provider_name']);
        });

        Schema::create('admission_integration_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_name', 80)->index();
            $table->string('event_type', 80)->index();
            $table->string('external_id')->nullable()->index();
            $table->morphs('subject');
            $table->foreignId('communication_log_id')->nullable()->constrained('admission_communication_logs')->nullOnDelete();
            $table->string('status', 40)->default('received')->index();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_provider_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_log_id')->nullable()->constrained('admission_communication_logs')->nullOnDelete();
            $table->string('provider_name', 80)->index();
            $table->string('channel', 40)->index();
            $table->string('status', 40)->default('queued')->index();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('attempted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_evaluator_assignment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->string('strategy', 40)->default('round_robin');
            $table->foreignId('fixed_evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('assigned_count')->default(0);
            $table->unsignedInteger('conflict_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_blind_scoring_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('alias_code')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['panel_id', 'applicant_id']);
        });

        Schema::create('admission_assessment_normalized_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('admission_assessment_panel_assignments')->cascadeOnDelete();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('raw_score', 8, 2)->default(0);
            $table->decimal('normalized_score', 8, 2)->default(0);
            $table->decimal('evaluator_mean', 8, 2)->default(0);
            $table->decimal('panel_mean', 8, 2)->default(0);
            $table->boolean('outlier_flag')->default(false)->index();
            $table->string('review_status', 40)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_script_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('stage', 60)->nullable()->index();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->json('steps');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_script_completion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_template_id')->constrained('admission_script_templates')->cascadeOnDelete();
            $table->foreignId('call_log_id')->nullable()->constrained('admission_call_logs')->nullOnDelete();
            $table->morphs('subject');
            $table->foreignId('counsellor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('step_results');
            $table->decimal('compliance_percent', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_objection_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 60)->default('general')->index();
            $table->string('recommended_response')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('admission_objection_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objection_type_id')->constrained('admission_objection_types')->cascadeOnDelete();
            $table->morphs('subject');
            $table->foreignId('counsellor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 60)->nullable()->index();
            $table->string('status', 40)->default('open')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_parent_journeys', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('preferred_channel', 30)->default('phone')->index();
            $table->string('decision_status', 60)->default('not_contacted')->index();
            $table->string('next_action')->nullable();
            $table->timestamp('next_due_at')->nullable()->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_automation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('admission_automations')->cascadeOnDelete();
            $table->string('trigger_window', 80)->default('daily')->index();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_automation_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->nullable()->constrained('admission_automations')->nullOnDelete();
            $table->string('trigger', 80)->index();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->unsignedInteger('matched_count')->default(0);
            $table->json('preview_actions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_automation_conflict_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->nullable()->constrained('admission_automations')->nullOnDelete();
            $table->morphs('subject');
            $table->string('conflict_key', 120)->index();
            $table->string('severity', 30)->default('medium')->index();
            $table->string('status', 40)->default('open')->index();
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_export_logs', function (Blueprint $table) {
            $table->id();
            $table->string('export_type', 80)->index();
            $table->string('surface', 80)->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach ([
            'admission_evaluator_scores' => ['assignment_id', 'evaluator_user_id'],
            'admission_assessment_panel_assignments' => ['panel_id', 'score_status'],
            'admission_assessment_lifecycle_events' => ['panel_id', 'to_status'],
            'admission_communication_logs' => ['provider', 'delivery_state'],
            'admission_automation_executions' => ['status', 'created_at'],
        ] as $table => $columns) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $table) {
                    $blueprint->index($columns, $table . '_v037_idx');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_export_logs');
        Schema::dropIfExists('admission_automation_conflict_logs');
        Schema::dropIfExists('admission_automation_simulations');
        Schema::dropIfExists('admission_automation_schedules');
        Schema::dropIfExists('admission_parent_journeys');
        Schema::dropIfExists('admission_objection_events');
        Schema::dropIfExists('admission_objection_types');
        Schema::dropIfExists('admission_script_completion_logs');
        Schema::dropIfExists('admission_script_templates');
        Schema::dropIfExists('admission_assessment_normalized_scores');
        Schema::dropIfExists('admission_blind_scoring_aliases');
        Schema::dropIfExists('admission_evaluator_assignment_batches');
        Schema::dropIfExists('admission_provider_delivery_attempts');
        Schema::dropIfExists('admission_integration_webhook_events');
        Schema::dropIfExists('admission_integration_providers');
    }
};
