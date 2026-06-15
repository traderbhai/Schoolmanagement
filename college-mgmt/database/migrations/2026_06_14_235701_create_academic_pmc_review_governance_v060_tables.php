<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_pmc_action_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('academic_pmc_work_items')->cascadeOnDelete();
            $table->foreignId('depends_on_work_item_id')->constrained('academic_pmc_work_items')->cascadeOnDelete();
            $table->string('dependency_type', 80)->default('blocked_by')->index();
            $table->string('status', 80)->default('active')->index();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['work_item_id', 'depends_on_work_item_id'], 'pmc_action_dependency_unique');
        });

        Schema::create('academic_pmc_action_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('academic_pmc_work_items')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reminder_type', 100)->default('due_followup')->index();
            $table->string('status', 80)->default('scheduled')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable()->index();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'status'], 'pmc_action_reminder_owner_status_idx');
        });

        Schema::create('academic_pmc_action_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained('academic_pmc_work_items')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('evidence_type', 100)->default('note')->index();
            $table->text('evidence_note')->nullable();
            $table->string('file_path')->nullable();
            $table->string('verification_status', 80)->default('submitted')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->index();
            $table->text('verification_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_action_evidence');
        Schema::dropIfExists('academic_pmc_action_reminders');
        Schema::dropIfExists('academic_pmc_action_dependencies');
    }
};
