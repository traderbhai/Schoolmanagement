<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_course_allocation_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_course_allocation_id')->nullable()->constrained('academic_pmc_student_course_allocations')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('exception_type', 80)->index();
            $table->string('status', 80)->default('requested')->index();
            $table->unsignedInteger('credit_delta')->default(0);
            $table->boolean('requires_dean_approval')->default(false)->index();
            $table->text('reason')->nullable();
            $table->json('validation_flags')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'term_id', 'status'], 'pmc_alloc_exception_student_term_idx');
            $table->index(['subject_id', 'term_id', 'exception_type'], 'pmc_alloc_exception_subject_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_course_allocation_exceptions');
    }
};
