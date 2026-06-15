<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_room_readiness_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->nullOnDelete();
            $table->unsignedInteger('scheduled_classes')->default(0);
            $table->unsignedInteger('max_group_strength')->default(0);
            $table->unsignedInteger('room_capacity')->default(0);
            $table->boolean('lab_required')->default(false)->index();
            $table->boolean('lab_ready')->default(false)->index();
            $table->boolean('capacity_ok')->default(true)->index();
            $table->string('readiness_band', 80)->default('ready')->index();
            $table->string('status', 80)->default('review_required')->index();
            $table->json('risk_reasons')->nullable();
            $table->json('usage_distribution')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'generation_run_id'], 'pmc_room_readiness_unique');
            $table->index(['generation_run_id', 'status', 'readiness_band'], 'pmc_room_ready_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_room_readiness_reviews');
    }
};
