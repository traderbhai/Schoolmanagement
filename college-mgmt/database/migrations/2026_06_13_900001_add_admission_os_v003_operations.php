<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_communication_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 30)->default('email')->index();
            $table->string('purpose', 60)->default('general')->index();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_communication_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('template_id')->nullable()->constrained('admission_communication_templates')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30)->index();
            $table->string('provider', 40)->default('internal')->index();
            $table->string('recipient')->nullable();
            $table->string('subject_line')->nullable();
            $table->text('body');
            $table->string('status', 30)->default('queued')->index();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_call_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('caller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('disposition', 40)->index();
            $table->string('outcome_reason')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('called_at')->nullable()->index();
            $table->timestamp('next_followup_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_pipeline_boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('object_type', 20)->default('lead')->index();
            $table->json('columns');
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_saved_views', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('surface', 60)->index();
            $table->string('role_name')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('filters')->nullable();
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('admission_automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger', 60)->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('admission_automations')->cascadeOnDelete();
            $table->morphs('subject');
            $table->string('idempotency_key')->unique();
            $table->string('status', 30)->default('completed')->index();
            $table->json('actions_taken')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_lead_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score')->default(0)->index();
            $table->string('band', 20)->index();
            $table->json('explanation')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scored_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('admission_journeys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_journey_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')->constrained('admission_journeys')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('stages');
            $table->json('documents')->nullable();
            $table->json('fee_milestones')->nullable();
            $table->json('session_rules')->nullable();
            $table->json('offer_rules')->nullable();
            $table->json('enrollment_blockers')->nullable();
            $table->text('applicant_instructions')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['journey_id', 'version']);
        });

        Schema::create('admission_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('agency')->index();
            $table->foreignId('contact_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->json('allowed_program_ids')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->string('commission_status', 30)->default('not_configured')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'admission_partner_id' => fn () => $table->foreignId('admission_partner_id')->nullable()->after('source')->constrained('admission_partners')->nullOnDelete(),
                'partner_reference' => fn () => $table->string('partner_reference')->nullable()->after('admission_partner_id'),
                'score_band' => fn () => $table->string('score_band', 20)->nullable()->after('priority')->index(),
            ] as $column => $definition) {
                if (!Schema::hasColumn('leads', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('applicants', function (Blueprint $table) {
            foreach ([
                'admission_partner_id' => fn () => $table->foreignId('admission_partner_id')->nullable()->after('batch_id')->constrained('admission_partners')->nullOnDelete(),
                'partner_reference' => fn () => $table->string('partner_reference')->nullable()->after('admission_partner_id'),
                'journey_version_id' => fn () => $table->foreignId('journey_version_id')->nullable()->after('partner_reference')->constrained('admission_journey_versions')->nullOnDelete(),
            ] as $column => $definition) {
                if (!Schema::hasColumn('applicants', $column)) {
                    $definition();
                }
            }
        });

        Schema::create('admission_forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable()->index();
            $table->unsignedInteger('target_seats')->default(0);
            $table->unsignedInteger('lead_count')->default(0);
            $table->unsignedInteger('application_count')->default(0);
            $table->unsignedInteger('selection_count')->default(0);
            $table->unsignedInteger('offer_count')->default(0);
            $table->unsignedInteger('enrollment_count')->default(0);
            $table->decimal('expected_conversion_rate', 6, 2)->default(0);
            $table->integer('projected_enrollments')->default(0);
            $table->integer('projected_gap')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_data_quality_flags', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('flag_type', 60)->index();
            $table->string('severity', 20)->default('warning')->index();
            $table->string('message');
            $table->string('status', 30)->default('open')->index();
            $table->decimal('confidence', 5, 2)->default(100);
            $table->json('metadata')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['flag_type', 'status']);
        });

        Schema::create('admission_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->string('action', 60)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_approvals');
        Schema::dropIfExists('admission_data_quality_flags');
        Schema::dropIfExists('admission_forecast_snapshots');
        Schema::dropIfExists('admission_partners');
        Schema::dropIfExists('admission_journey_versions');
        Schema::dropIfExists('admission_journeys');
        Schema::dropIfExists('admission_lead_scores');
        Schema::dropIfExists('admission_automation_executions');
        Schema::dropIfExists('admission_automations');
        Schema::dropIfExists('admission_saved_views');
        Schema::dropIfExists('admission_pipeline_boards');
        Schema::dropIfExists('admission_call_logs');
        Schema::dropIfExists('admission_communication_logs');
        Schema::dropIfExists('admission_communication_templates');
    }
};
