<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_faculty_availability_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('request_type', 100)->default('availability_update')->index();
            $table->string('status', 80)->default('submitted')->index();
            $table->json('available_days')->nullable();
            $table->json('preferred_slots')->nullable();
            $table->json('unavailable_slots')->nullable();
            $table->unsignedInteger('max_classes_per_day')->nullable();
            $table->unsignedInteger('max_consecutive_classes')->nullable();
            $table->unsignedInteger('max_weekly_load')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'term_id', 'status'], 'pmc_fac_avail_teacher_term_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_faculty_availability_requests');
    }
};
