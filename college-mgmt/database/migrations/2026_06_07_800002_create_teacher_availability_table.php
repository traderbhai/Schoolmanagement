<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teacher_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('day_of_week'); // 1=Mon … 6=Sat
            $table->foreignId('timetable_slot_id')->constrained()->cascadeOnDelete();
            $table->enum('availability', ['available','unavailable','preferred'])->default('available');
            $table->string('notes', 200)->nullable();
            $table->unique(['teacher_id','term_id','day_of_week','timetable_slot_id']);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('teacher_availability');
    }
};
