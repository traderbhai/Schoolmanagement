<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_faculty_load_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->nullOnDelete();
            $table->unsignedInteger('assigned_weekly_hours')->default(0);
            $table->unsignedInteger('scheduled_classes')->default(0);
            $table->unsignedInteger('max_classes_in_day')->default(0);
            $table->unsignedInteger('max_consecutive_classes')->default(0);
            $table->unsignedInteger('configured_weekly_limit')->default(0);
            $table->unsignedInteger('configured_daily_limit')->default(0);
            $table->string('load_band', 80)->default('normal')->index();
            $table->string('status', 80)->default('review_required')->index();
            $table->json('risk_reasons')->nullable();
            $table->json('daily_distribution')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'term_id', 'generation_run_id'], 'pmc_fac_load_review_unique');
            $table->index(['term_id', 'status', 'load_band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_faculty_load_reviews');
    }
};
