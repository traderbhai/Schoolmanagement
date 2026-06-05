<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        // --- A. Migrate courses → programs (preserving IDs so FKs still work) ---
        DB::table('courses')->get()->each(function ($course) {
            DB::table('programs')->insertOrIgnore([
                'id'                      => $course->id,
                'department_id'           => $course->department_id,
                'name'                    => $course->name,
                'code'                    => $course->code,
                'abbreviation'            => $course->code,
                'system_type'             => 'semester',
                'duration_years'          => $course->duration_years ?? 2,
                'total_terms'             => $course->total_semesters ?? 4,
                'description'             => $course->description,
                'default_intake_capacity' => 60,
                'is_active'               => $course->is_active ?? 1,
                'created_at'              => $course->created_at,
                'updated_at'              => $course->updated_at,
            ]);
        });

        // --- B. Create a default batch per program and migrate semesters → terms ---
        $currentAcademicYearId = DB::table('academic_years')->where('is_current', true)->value('id');

        DB::table('programs')->get()->each(function ($prog) use ($currentAcademicYearId) {
            $batchId = DB::table('batches')->insertGetId([
                'program_id'       => $prog->id,
                'academic_year_id' => $currentAcademicYearId,
                'name'             => $prog->name . ' Default Batch',
                'code'             => substr($prog->code, 0, 13) . '-DEFAULT',
                'start_date'       => now()->format('Y-m-d'),
                'end_date'         => now()->addYears($prog->duration_years)->format('Y-m-d'),
                'intake_capacity'  => $prog->default_intake_capacity,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Migrate semesters → terms for this batch
            DB::table('semesters')->get()->each(function ($sem) use ($batchId, $prog) {
                DB::table('terms')->insertOrIgnore([
                    'batch_id'    => $batchId,
                    'program_id'  => $prog->id,
                    'term_number' => $sem->number ?? $sem->id,
                    'name'        => $sem->name,
                    'start_date'  => $sem->start_date,
                    'end_date'    => $sem->end_date,
                    'is_current'  => $sem->is_current ?? 0,
                    'sort_order'  => $sem->number ?? $sem->id,
                    'created_at'  => $sem->created_at,
                    'updated_at'  => $sem->updated_at,
                ]);
            });
        });

        // --- C. Add program_id + batch_id + specialization_id to students ---
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('program_id')->constrained()->nullOnDelete();
            $table->foreignId('specialization_id')->nullable()->after('batch_id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('current_term')->nullable()->after('current_semester');
        });
        DB::statement('UPDATE students SET program_id = course_id, current_term = current_semester WHERE program_id IS NULL');
        // Assign default batch per program
        DB::table('students')->whereNull('batch_id')->get()->each(function ($s) {
            $batch = DB::table('batches')->where('program_id', $s->program_id)->first();
            if ($batch) {
                DB::table('students')->where('id', $s->id)->update(['batch_id' => $batch->id]);
            }
        });

        // --- D. Add program_id + term_number to subjects ---
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('term_number')->nullable()->after('program_id');
        });

        // --- E. Add program_id + term_id to timetable_entries ---
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('semester_id')->constrained('terms')->nullOnDelete();
        });
        DB::statement('UPDATE timetable_entries SET program_id = course_id WHERE program_id IS NULL');
        // Map semester_id → term_id via semester name
        DB::table('terms')->get()->each(function ($term) {
            $semId = DB::table('semesters')->where('name', $term->name)->value('id');
            if ($semId) {
                DB::table('timetable_entries')->where('semester_id', $semId)->update(['term_id' => $term->id]);
            }
        });

        // --- F. Add program_id + term_id to exams ---
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('semester_id')->constrained()->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('program_id')->constrained('terms')->nullOnDelete();
        });
        // exams had course_id in the seeder but the original migration didn't have it
        // Only copy if course_id column exists
        if (Schema::hasColumn('exams', 'course_id')) {
            DB::statement('UPDATE exams SET program_id = course_id WHERE program_id IS NULL');
        }
        DB::table('terms')->get()->each(function ($term) {
            $semId = DB::table('semesters')->where('name', $term->name)->value('id');
            if ($semId) {
                DB::table('exams')->where('semester_id', $semId)->update(['term_id' => $term->id]);
            }
        });

        // --- G. Add program_id + batch_id to fee_structures ---
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('program_id')->constrained()->nullOnDelete();
        });
        DB::statement('UPDATE fee_structures SET program_id = course_id WHERE program_id IS NULL');

        // --- H. Add program_id + batch_id to admissions ---
        Schema::table('admissions', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('program_id')->constrained()->nullOnDelete();
        });
        DB::statement('UPDATE admissions SET program_id = course_id WHERE program_id IS NULL');

        // --- I. Add term_id to enrollments ---
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('term_id')->nullable()->after('semester_id')->constrained('terms')->nullOnDelete();
        });
        DB::table('terms')->get()->each(function ($term) {
            $semId = DB::table('semesters')->where('name', $term->name)->value('id');
            if ($semId) {
                DB::table('enrollments')->where('semester_id', $semId)->update(['term_id' => $term->id]);
            }
        });
    }

    public function down(): void
    {
        foreach (['enrollments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('term_id');
            });
        }
        Schema::table('admissions', function (Blueprint $t) {
            $t->dropColumn(['program_id', 'batch_id']);
        });
        Schema::table('fee_structures', function (Blueprint $t) {
            $t->dropColumn(['program_id', 'batch_id']);
        });
        Schema::table('exams', function (Blueprint $t) {
            $t->dropColumn(['program_id', 'term_id']);
        });
        Schema::table('timetable_entries', function (Blueprint $t) {
            $t->dropColumn(['program_id', 'term_id']);
        });
        Schema::table('subjects', function (Blueprint $t) {
            $t->dropColumn(['program_id', 'term_number']);
        });
        Schema::table('students', function (Blueprint $t) {
            $t->dropColumn(['program_id', 'batch_id', 'specialization_id', 'current_term']);
        });
        DB::table('terms')->truncate();
        DB::table('batches')->truncate();
        DB::table('specializations')->truncate();
        DB::table('programs')->truncate();
    }
};
