<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_timetable_resolution_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('constraint_id')->constrained('academic_pmc_timetable_constraints')->cascadeOnDelete();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->nullOnDelete();
            $table->string('action_type', 100)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 40)->default('normal')->index();
            $table->string('status', 80)->default('open')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->text('resolution_note')->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['generation_run_id', 'status']);
            $table->index(['owner_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_timetable_resolution_actions');
    }
};
