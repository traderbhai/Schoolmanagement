<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['compulsory', 'elective'])->default('compulsory');
            $table->string('elective_group')->nullable(); // e.g. "Group A", "Group B"
            $table->integer('credits')->default(3);
            $table->integer('max_elective_choices')->nullable(); // how many from this group a student must pick
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['program_id', 'subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_subjects');
    }
};
