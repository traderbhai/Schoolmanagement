<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('marks_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_result_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->text('description');
            $table->decimal('marks_claimed', 5, 2)->nullable();
            $table->enum('status', ['pending','under_review','resolved','rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remarks')->nullable();
            $table->decimal('revised_marks', 5, 2)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('marks_appeals'); }
};
