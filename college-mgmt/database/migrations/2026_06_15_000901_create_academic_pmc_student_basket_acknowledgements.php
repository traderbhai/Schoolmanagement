<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_student_basket_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_course_allocation_id')->nullable()->constrained('academic_pmc_student_course_allocations')->nullOnDelete();
            $table->foreignId('timetable_version_id')->nullable()->constrained('timetable_versions')->nullOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->nullOnDelete();
            $table->string('acknowledgement_type')->default('allocation_review');
            $table->string('status')->default('submitted');
            $table->string('reason')->nullable();
            $table->text('student_note')->nullable();
            $table->text('pmc_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'acknowledgement_type'], 'pmc_basket_ack_student_status_type_idx');
            $table->index('student_course_allocation_id', 'pmc_basket_ack_allocation_idx');
            $table->index('timetable_version_id', 'pmc_basket_ack_version_idx');
            $table->index('generation_run_id', 'pmc_basket_ack_run_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_student_basket_acknowledgements');
    }
};
