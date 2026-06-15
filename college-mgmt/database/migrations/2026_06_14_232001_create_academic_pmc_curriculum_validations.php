<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_curriculum_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_plan_id')->nullable()->constrained('academic_pmc_curriculum_plans')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('validation_type', 80)->index();
            $table->string('status', 60)->default('pending')->index();
            $table->string('severity', 40)->default('medium')->index();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('title');
            $table->text('details')->nullable();
            $table->text('recommended_action')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_plan_id', 'subject_id', 'validation_type'], 'pmc_curriculum_validation_unique');
            $table->index(['program_id', 'status']);
            $table->index(['subject_id', 'status']);
            $table->index(['validation_type', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_curriculum_validations');
    }
};
