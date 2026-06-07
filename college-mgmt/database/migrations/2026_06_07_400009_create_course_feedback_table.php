<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('teaching_rating')->nullable();   // 1-5
            $table->integer('content_rating')->nullable();    // 1-5
            $table->integer('overall_rating')->nullable();    // 1-5
            $table->text('comments')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->timestamps();
            $table->unique(['student_id', 'subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_feedback');
    }
};
