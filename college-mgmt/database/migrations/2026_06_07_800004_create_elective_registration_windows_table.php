<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('elective_registration_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('elective_group')->nullable(); // null = applies to all elective groups
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->unsignedTinyInteger('max_selections')->default(1); // how many electives student can pick
            $table->enum('status', ['draft','open','closed','finalized'])->default('draft');
            $table->text('instructions')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('elective_registration_windows');
    }
};
