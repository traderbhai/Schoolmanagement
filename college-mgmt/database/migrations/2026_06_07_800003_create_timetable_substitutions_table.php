<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('timetable_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_entry_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->enum('action', ['substitute','cancelled','rescheduled'])->default('substitute');
            $table->string('reason', 300)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('timetable_substitutions');
    }
};
