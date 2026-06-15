<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_planning_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('cycle_type', 100)->index();
            $table->string('academic_year', 40)->nullable()->index();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('draft')->index();
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['program_id', 'cycle_type', 'status'], 'pmc_plan_program_type_status_idx');
            $table->index(['term_id', 'status'], 'pmc_plan_term_status_idx');
        });

        Schema::create('academic_pmc_readiness_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_cycle_id')->constrained('academic_pmc_planning_cycles')->cascadeOnDelete();
            $table->string('section', 100)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 80)->default('open')->index();
            $table->string('severity', 40)->default('normal')->index();
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->boolean('is_blocker')->default(false)->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->string('source_type', 100)->nullable()->index();
            $table->string('source_key')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['planning_cycle_id', 'status'], 'pmc_ready_cycle_status_idx');
            $table->index(['owner_user_id', 'status'], 'pmc_ready_owner_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_readiness_items');
        Schema::dropIfExists('academic_pmc_planning_cycles');
    }
};
