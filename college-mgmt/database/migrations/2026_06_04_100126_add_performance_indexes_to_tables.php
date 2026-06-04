<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'timetable_entry_id'], 'idx_att_student_entry');
            $table->index(['timetable_entry_id', 'date'], 'idx_att_entry_date');
        });

        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->index(['semester_id', 'day_of_week'], 'idx_tt_sem_day');
            $table->index(['teacher_id', 'semester_id'], 'idx_tt_teacher_sem');
            $table->index(['course_id', 'semester_id'], 'idx_tt_course_sem');
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->index(['exam_id', 'student_id'], 'idx_er_exam_student');
            $table->index(['student_id'], 'idx_er_student');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['student_id', 'semester_id'], 'idx_enr_student_sem');
            $table->index(['course_id', 'semester_id'], 'idx_enr_course_sem');
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->index(['student_id'], 'idx_fee_student');
            $table->index(['fee_structure_id', 'student_id'], 'idx_fee_struct_student');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_att_student_entry');
            $table->dropIndex('idx_att_entry_date');
        });
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropIndex('idx_tt_sem_day');
            $table->dropIndex('idx_tt_teacher_sem');
            $table->dropIndex('idx_tt_course_sem');
        });
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropIndex('idx_er_exam_student');
            $table->dropIndex('idx_er_student');
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enr_student_sem');
            $table->dropIndex('idx_enr_course_sem');
        });
        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex('idx_fee_student');
            $table->dropIndex('idx_fee_struct_student');
        });
    }
};
