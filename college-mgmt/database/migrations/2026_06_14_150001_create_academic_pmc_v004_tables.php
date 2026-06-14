<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_operating_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 100)->index();
            $table->string('category', 100)->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('open')->index();
            $table->string('priority', 40)->default('normal')->index();
            $table->string('risk_band', 40)->nullable()->index();
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->string('source_route')->nullable();
            $table->json('metrics')->nullable();
            $table->json('checklist')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['record_type', 'status']);
            $table->index(['category', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index(['program_id', 'record_type']);
        });

        Schema::create('academic_pmc_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approval_type', 100)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('pending')->index();
            $table->string('sla_status', 40)->default('on_track')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('decided_at')->nullable()->index();
            $table->text('decision_reason')->nullable();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['approval_type', 'status']);
        });

        Schema::create('academic_pmc_review_governance_records', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 80)->index();
            $table->foreignId('meeting_id')->nullable()->constrained('academic_pmc_review_meetings')->nullOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained('academic_pmc_work_items')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('open')->index();
            $table->string('decision_type', 80)->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_pmc_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger_key', 100)->index();
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('academic_pmc_automation_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->nullable()->constrained('academic_pmc_automation_rules')->nullOnDelete();
            $table->string('subject_type', 100)->nullable()->index();
            $table->string('subject_key')->nullable()->index();
            $table->string('idempotency_key')->unique();
            $table->string('status', 60)->default('executed')->index();
            $table->text('result')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('executed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('academic_pmc_analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_type', 100)->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->date('snapshot_date')->index();
            $table->string('band', 40)->nullable()->index();
            $table->unsignedInteger('score')->default(0);
            $table->json('metrics')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['snapshot_type', 'snapshot_date']);
        });

        Schema::create('academic_pmc_policy_audits', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->unique();
            $table->string('method', 20)->default('GET');
            $table->string('required_scope')->nullable();
            $table->string('risk_level', 40)->default('medium')->index();
            $table->boolean('middleware_present')->default(true);
            $table->boolean('policy_present')->default(true);
            $table->string('last_test_status', 60)->default('pending')->index();
            $table->boolean('missing_enforcement')->default(false)->index();
            $table->json('roles_tested')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_policy_audits');
        Schema::dropIfExists('academic_pmc_analytics_snapshots');
        Schema::dropIfExists('academic_pmc_automation_executions');
        Schema::dropIfExists('academic_pmc_automation_rules');
        Schema::dropIfExists('academic_pmc_review_governance_records');
        Schema::dropIfExists('academic_pmc_approvals');
        Schema::dropIfExists('academic_pmc_operating_records');
    }
};
