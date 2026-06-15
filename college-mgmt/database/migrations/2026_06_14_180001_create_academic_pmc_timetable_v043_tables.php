<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_timetable_version_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_version_id')->constrained('timetable_versions')->cascadeOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->nullOnDelete();
            $table->string('lifecycle_status', 80)->default('published')->index();
            $table->string('approval_status', 80)->default('pmc_published')->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('frozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable()->index();
            $table->foreignId('unfrozen_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unfrozen_at')->nullable();
            $table->foreignId('rollback_from_version_id')->nullable()->constrained('timetable_versions')->nullOnDelete();
            $table->text('decision_reason')->nullable();
            $table->text('override_reason')->nullable();
            $table->json('publish_summary')->nullable();
            $table->json('impact_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lifecycle_status', 'approval_status']);
            $table->index(['generation_run_id', 'timetable_version_id'], 'pmc_tt_workflow_run_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_timetable_version_workflows');
    }
};
