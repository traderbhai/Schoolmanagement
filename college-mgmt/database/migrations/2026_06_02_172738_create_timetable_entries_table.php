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
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_slot_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week'); // 1=Mon ... 6=Sat
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Prevent double-booking: same room/slot/day
            $table->unique(['classroom_id', 'timetable_slot_id', 'day_of_week', 'semester_id'], 'unique_room_slot');
            // Prevent teacher clash: same teacher/slot/day
            $table->unique(['teacher_id', 'timetable_slot_id', 'day_of_week', 'semester_id'], 'unique_teacher_slot');
            // Prevent course clash: same course/slot/day
            $table->unique(['course_id', 'timetable_slot_id', 'day_of_week', 'semester_id'], 'unique_course_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
