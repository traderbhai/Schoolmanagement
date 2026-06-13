<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_handoff_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enrollment_confirmation_id')->nullable()->constrained('enrollment_confirmations')->nullOnDelete();
            $table->string('status', 60)->default('pending_admission_completion')->index();
            $table->json('blockers')->nullable();
            $table->json('verified_document_summary')->nullable();
            $table->json('fee_clearance_summary')->nullable();
            $table->json('joining_kit_summary')->nullable();
            $table->string('orientation_status', 60)->nullable()->index();
            $table->text('handoff_notes')->nullable();
            $table->foreignId('handed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handed_off_at')->nullable()->index();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('applicant_id');
        });

        Schema::create('admission_policy_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->nullable()->index();
            $table->string('ability', 100)->index();
            $table->string('method', 10)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 120)->nullable()->index();
            $table->string('expected_scope', 120)->nullable();
            $table->string('result', 20)->index();
            $table->nullableMorphs('subject');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_transition_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('transition_key', 100)->nullable()->index();
            $table->string('from_status', 80)->nullable()->index();
            $table->string('to_status', 80)->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->boolean('blocked')->default(false)->index();
            $table->json('blockers')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_blocked_communications', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('subject');
            $table->foreignId('template_id')->nullable()->constrained('admission_communication_templates')->nullOnDelete();
            $table->string('channel', 40)->index();
            $table->string('recipient')->nullable()->index();
            $table->string('blocked_by_rule', 120)->index();
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->string('status', 40)->default('blocked')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_export_view_logs', function (Blueprint $table) {
            $table->id();
            $table->string('export_type', 100)->index();
            $table->string('surface', 120)->nullable()->index();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('file_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_high_volume_seed_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->unsignedInteger('lead_count')->default(0);
            $table->unsignedInteger('applicant_count')->default(0);
            $table->unsignedInteger('communication_count')->default(0);
            $table->string('status', 40)->default('planned')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_high_volume_seed_runs');
        Schema::dropIfExists('admission_export_view_logs');
        Schema::dropIfExists('admission_blocked_communications');
        Schema::dropIfExists('admission_transition_events');
        Schema::dropIfExists('admission_policy_audit_logs');
        Schema::dropIfExists('admission_handoff_records');
    }
};
