<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_course_group_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_group_id')->constrained('academic_pmc_course_groups')->cascadeOnDelete();
            $table->foreignId('target_course_group_id')->nullable()->constrained('academic_pmc_course_groups')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('adjustment_type', 80)->index();
            $table->string('status', 80)->default('requested')->index();
            $table->unsignedInteger('from_strength')->default(0);
            $table->unsignedInteger('to_strength')->default(0);
            $table->unsignedInteger('target_from_strength')->default(0);
            $table->unsignedInteger('target_to_strength')->default(0);
            $table->boolean('requires_dean_approval')->default(false)->index();
            $table->text('reason')->nullable();
            $table->json('impact_summary')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['adjustment_type', 'status'], 'pmc_group_adjust_type_status_idx');
            $table->index(['course_group_id', 'target_course_group_id'], 'pmc_group_adjust_groups_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_course_group_adjustments');
    }
};
