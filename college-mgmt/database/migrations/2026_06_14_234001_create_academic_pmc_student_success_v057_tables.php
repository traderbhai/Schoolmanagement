<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_student_interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_success_plan_id')->nullable()->constrained('academic_pmc_student_success_plans')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('intervention_type', 100)->index();
            $table->string('status', 80)->default('open')->index();
            $table->string('priority', 40)->default('normal')->index();
            $table->text('reason')->nullable();
            $table->text('action_plan')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status'], 'pmc_intervention_student_status_idx');
            $table->index(['owner_user_id', 'status'], 'pmc_intervention_owner_status_idx');
        });

        Schema::create('academic_pmc_parent_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_success_plan_id')->nullable()->constrained('academic_pmc_student_success_plans')->nullOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('academic_pmc_student_interventions')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('reason', 160)->index();
            $table->string('status', 80)->default('scheduled')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->text('outcome_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'status'], 'pmc_parent_esc_student_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_parent_escalations');
        Schema::dropIfExists('academic_pmc_student_interventions');
    }
};
