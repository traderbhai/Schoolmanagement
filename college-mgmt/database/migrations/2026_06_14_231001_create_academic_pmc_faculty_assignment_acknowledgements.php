<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_faculty_assignment_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_faculty_assignment_id')->constrained('academic_pmc_group_faculty_assignments')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->string('status', 80)->default('pending')->index();
            $table->string('response_type', 80)->nullable()->index();
            $table->text('faculty_note')->nullable();
            $table->json('constraints_raised')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['group_faculty_assignment_id', 'teacher_id'], 'pmc_fac_assign_ack_unique');
            $table->index(['teacher_id', 'status'], 'pmc_fac_assign_ack_teacher_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_faculty_assignment_acknowledgements');
    }
};
