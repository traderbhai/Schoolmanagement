<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_transcripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('academic_year')->nullable();
            $table->decimal('cgpa', 4, 2)->default(0);
            $table->integer('total_credits_earned')->default(0);
            $table->string('status')->default('draft'); // draft, issued, archived
            $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('issued_at')->nullable();
            $table->json('semester_data')->nullable(); // snapshot of semester results
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_transcripts');
    }
};
