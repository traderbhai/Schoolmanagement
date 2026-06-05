<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drive_id')->constrained('placement_drives')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('application_status', ['applied', 'shortlisted', 'interview', 'selected', 'rejected', 'withdrawn'])->default('applied');
            $table->string('offer_letter_number')->nullable();
            $table->decimal('offered_package', 10, 2)->nullable();
            $table->date('joining_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['drive_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
