<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_result_id')->constrained()->onDelete('cascade');
            $table->enum('component_type', ['ia1', 'ia2', 'end_semester'])->comment('IA1, IA2, or End Semester');
            $table->decimal('marks_obtained', 5, 2);
            $table->decimal('max_marks', 5, 2)->default(10);
            $table->text('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('marked_at')->nullable();
            $table->timestamps();
            $table->index('exam_result_id');
            $table->index('component_type');
            $table->unique(['exam_result_id', 'component_type']);
        });

        // Modify exam_results table to include component scores if not exists
        Schema::table('exam_results', function (Blueprint $table) {
            // Check if columns don't already exist before adding
            if (!Schema::hasColumn('exam_results', 'ia1_marks')) {
                $table->decimal('ia1_marks', 5, 2)->nullable()->after('marks_obtained');
                $table->decimal('ia2_marks', 5, 2)->nullable()->after('ia1_marks');
                $table->decimal('end_sem_marks', 5, 2)->nullable()->after('ia2_marks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};
