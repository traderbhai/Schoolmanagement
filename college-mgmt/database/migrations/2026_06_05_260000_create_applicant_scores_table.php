<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selection_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selection_process_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scored_by')->constrained('users');
            $table->json('parameter_scores')->nullable();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_possible_score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();

            $table->unique(['applicant_id', 'selection_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_scores');
    }
};
