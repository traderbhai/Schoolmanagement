<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('confirmed_by')->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('enrollment_number')->unique();
            $table->string('roll_number');
            $table->foreignId('batch_id')->constrained('batches');
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_confirmations');
    }
};
