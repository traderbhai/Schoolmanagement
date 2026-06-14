<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_dean_planning_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('cycle_type', 60)->index();
            $table->string('academic_year', 40)->nullable()->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('branch', 80)->default('dean_office')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('draft')->index();
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_readiness_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_cycle_id')->constrained('academic_dean_planning_cycles')->cascadeOnDelete();
            $table->string('section', 80)->index();
            $table->string('title');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('pending')->index();
            $table->boolean('is_blocker')->default(false)->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_review_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('review_type', 80)->index();
            $table->string('recurrence', 40)->default('weekly')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('agenda_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('academic_dean_review_meetings')->cascadeOnDelete();
            $table->text('minutes');
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained('academic_dean_review_meetings')->nullOnDelete();
            $table->string('title');
            $table->string('decision_type', 80)->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('open')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->text('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_action_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained('academic_dean_action_items')->cascadeOnDelete();
            $table->string('event_type', 80)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_action_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained('academic_dean_action_items')->cascadeOnDelete();
            $table->foreignId('depends_on_action_id')->constrained('academic_dean_action_items')->cascadeOnDelete();
            $table->string('dependency_type', 50)->default('blocked_by')->index();
            $table->timestamps();
            $table->unique(['action_item_id', 'depends_on_action_id']);
        });

        Schema::create('academic_dean_action_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_item_id')->constrained('academic_dean_action_items')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('path')->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_status', 50)->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('academic_dean_risk_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('dimension', 80)->index();
            $table->string('scope_type', 50)->default('department')->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->unsignedInteger('medium_threshold')->default(20);
            $table->unsignedInteger('high_threshold')->default(40);
            $table->unsignedInteger('critical_threshold')->default(70);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_risk_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('branch', 80)->nullable()->index();
            $table->unsignedInteger('score')->default(0);
            $table->string('band', 40)->default('low')->index();
            $table->string('trend', 40)->default('stable')->index();
            $table->json('metrics')->nullable();
            $table->json('reasons')->nullable();
            $table->date('snapshot_date')->index();
            $table->timestamps();
        });

        Schema::create('academic_dean_risk_mitigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_snapshot_id')->nullable()->constrained('academic_dean_risk_snapshots')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('new')->index();
            $table->text('plan');
            $table->timestamp('due_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_approval_items', function (Blueprint $table) {
            $table->id();
            $table->string('approval_type', 100)->index();
            $table->string('title');
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('pending')->index();
            $table->string('risk_level', 40)->default('normal')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->text('decision_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_operating_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 100)->index();
            $table->string('title');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('open')->index();
            $table->string('severity', 40)->default('normal')->index();
            $table->integer('score')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->json('metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['record_type', 'status']);
            $table->index(['record_type', 'severity']);
        });

        Schema::create('academic_dean_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 80)->index();
            $table->string('title');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 50)->default('scheduled')->index();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_report_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pack_type', 80)->index();
            $table->string('schedule', 80)->default('manual')->index();
            $table->string('status', 50)->default('active')->index();
            $table->timestamp('last_generated_at')->nullable()->index();
            $table->json('filters')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_policy_audits', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->index();
            $table->string('method', 20)->default('GET')->index();
            $table->string('expected_roles')->nullable();
            $table->string('risk_level', 40)->default('read')->index();
            $table->boolean('has_policy')->default(false)->index();
            $table->string('last_test_status', 60)->default('not_tested')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_dean_policy_audits');
        Schema::dropIfExists('academic_dean_report_packs');
        Schema::dropIfExists('academic_dean_calendar_events');
        Schema::dropIfExists('academic_dean_operating_records');
        Schema::dropIfExists('academic_dean_approval_items');
        Schema::dropIfExists('academic_dean_risk_mitigations');
        Schema::dropIfExists('academic_dean_risk_snapshots');
        Schema::dropIfExists('academic_dean_risk_thresholds');
        Schema::dropIfExists('academic_dean_action_evidence');
        Schema::dropIfExists('academic_dean_action_dependencies');
        Schema::dropIfExists('academic_dean_action_events');
        Schema::dropIfExists('academic_dean_decisions');
        Schema::dropIfExists('academic_dean_meeting_minutes');
        Schema::dropIfExists('academic_dean_review_templates');
        Schema::dropIfExists('academic_dean_readiness_items');
        Schema::dropIfExists('academic_dean_planning_cycles');
    }
};
