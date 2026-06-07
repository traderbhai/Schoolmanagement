<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('exam_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending','approved','rejected'])->default('pending');
            $table->boolean('attendance_eligible')->default(false);
            $table->boolean('fee_cleared')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id','exam_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('exam_registrations'); }
};
