<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('academic_pmc_timetable_session_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_run_id')->nullable()->constrained('academic_pmc_timetable_generation_runs')->cascadeOnDelete();
            $table->foreignId('course_group_id')->constrained('academic_pmc_course_groups')->cascadeOnDelete();
            $table->string('session_type', 80)->default('lecture')->index();
            $table->unsignedInteger('required_sessions_per_week')->default(1);
            $table->unsignedInteger('duration_slots')->default(1);
            $table->unsignedInteger('scheduled_sessions')->default(0);
            $table->unsignedInteger('unscheduled_sessions')->default(0);
            $table->string('status', 80)->default('pending')->index();
            $table->string('source', 80)->default('faculty_weekly_hours')->index();
            $table->json('rules')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['course_group_id', 'session_type'], 'pmc_tt_demand_group_type_idx');
            $table->index(['generation_run_id', 'status'], 'pmc_tt_demand_run_status_idx');
        });

        Schema::table('academic_pmc_timetable_generation_items', function (Blueprint $table) {
            $table->foreignId('session_demand_id')->nullable()->after('course_group_id')->constrained('academic_pmc_timetable_session_demands')->nullOnDelete();
            $table->unsignedInteger('session_index')->default(1)->after('session_demand_id');
            $table->string('session_type', 80)->default('lecture')->after('session_index')->index();
            $table->unsignedInteger('duration_slots')->default(1)->after('session_type');
            $table->foreignId('operational_timetable_entry_id')->nullable()->after('timetable_slot_id')->constrained('timetable_entries')->nullOnDelete();
            $table->index(['course_group_id', 'session_type', 'session_index'], 'pmc_tt_item_group_session_idx');
        });
    }

    public function down(): void
    {
        Schema::table('academic_pmc_timetable_generation_items', function (Blueprint $table) {
            $table->dropForeign(['session_demand_id']);
            $table->dropForeign(['operational_timetable_entry_id']);
            $table->dropIndex('pmc_tt_item_group_session_idx');
            $table->dropColumn(['session_demand_id', 'session_index', 'session_type', 'duration_slots', 'operational_timetable_entry_id']);
        });

        Schema::dropIfExists('academic_pmc_timetable_session_demands');
    }
};
