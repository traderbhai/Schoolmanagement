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
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('applicant_name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('category', 30)->nullable();
            $table->foreignId('course_id')->constrained()->nullOnDelete();
            $table->string('last_qualification')->nullable();
            $table->string('last_institution')->nullable();
            $table->decimal('last_percentage', 5, 2)->nullable();
            $table->date('application_date')->nullable();
            $table->enum('status', ['enquiry', 'applied', 'shortlisted', 'admitted', 'rejected', 'withdrawn'])->default('enquiry');
            $table->text('remarks')->nullable();
            $table->foreignId('converted_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
