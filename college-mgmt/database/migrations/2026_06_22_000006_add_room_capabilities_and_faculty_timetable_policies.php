<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_pmc_room_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('capability_type', 80)->index();
            $table->string('capability_key', 120)->index();
            $table->string('capability_value')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['classroom_id', 'capability_type', 'capability_key'], 'pmc_room_cap_unique');
        });

        Schema::create('academic_pmc_faculty_timetable_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedInteger('max_consecutive_classes')->default(3);
            $table->unsignedInteger('max_daily_classes')->default(4);
            $table->boolean('requires_lunch_gap')->default(false);
            $table->json('allowed_days')->nullable();
            $table->json('unavailable_periods')->nullable();
            $table->json('campus_gap_rules')->nullable();
            $table->string('status', 80)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'term_id', 'status'], 'pmc_fac_tt_policy_teacher_term_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_faculty_timetable_policies');
        Schema::dropIfExists('academic_pmc_room_capabilities');
    }
};
