<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_course_delivery_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('planned_sessions')->default(0);
            $table->unsignedInteger('conducted_sessions')->default(0);
            $table->unsignedInteger('missed_sessions')->default(0);
            $table->unsignedInteger('marks_pending_count')->default(0);
            $table->decimal('attendance_percent', 5, 2)->default(0);
            $table->decimal('feedback_score', 4, 2)->nullable();
            $table->unsignedTinyInteger('delivery_score')->default(0);
            $table->string('risk_band', 40)->default('low')->index();
            $table->string('status', 80)->default('monitoring')->index();
            $table->timestamp('next_review_at')->nullable()->index();
            $table->json('signals')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['subject_id', 'term_id'], 'pmc_delivery_subject_term_unique');
            $table->index(['program_id', 'risk_band'], 'pmc_delivery_program_risk_idx');
            $table->index(['owner_user_id', 'status'], 'pmc_delivery_owner_status_idx');
        });

        Schema::create('academic_pmc_remedial_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_id')->nullable()->constrained('academic_pmc_course_delivery_checkpoints')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 100)->index();
            $table->string('status', 80)->default('open')->index();
            $table->string('priority', 40)->default('normal')->index();
            $table->text('reason')->nullable();
            $table->text('action_plan')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'status'], 'pmc_remedial_subject_status_idx');
            $table->index(['owner_user_id', 'status'], 'pmc_remedial_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_remedial_actions');
        Schema::dropIfExists('academic_pmc_course_delivery_checkpoints');
    }
};
