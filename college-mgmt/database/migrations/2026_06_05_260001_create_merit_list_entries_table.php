<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->integer('rank');
            $table->decimal('total_weighted_score', 8, 2)->default(0);
            $table->json('step_scores')->nullable();
            $table->decimal('academic_score', 5, 2)->nullable();
            $table->decimal('composite_score', 8, 2)->default(0);
            $table->integer('merit_list_version')->default(1);
            $table->enum('decision', ['pending', 'selected', 'waitlisted', 'rejected'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_list_entries');
    }
};
