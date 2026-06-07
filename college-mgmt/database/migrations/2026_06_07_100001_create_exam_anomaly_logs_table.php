<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exam_anomaly_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('anomaly_type'); // malpractice, absent_without_reason, late_entry, paper_leak, other
            $table->text('description');
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->string('action_taken')->nullable(); // warning, debarred, cancelled, none
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_anomaly_logs');
    }
};
