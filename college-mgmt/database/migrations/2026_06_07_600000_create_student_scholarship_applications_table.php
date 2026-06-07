<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_scholarship_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scholarship_scheme_id')->constrained('scholarship_schemes')->cascadeOnDelete();
            $table->decimal('cgpa_at_application', 4, 2)->nullable();
            $table->text('reason');
            $table->string('documents_path')->nullable();
            $table->enum('status', ['pending','shortlisted','approved','rejected','disbursed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->dateTime('disbursed_at')->nullable();
            $table->decimal('disbursed_amount', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['student_id','scholarship_scheme_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('student_scholarship_applications'); }
};
