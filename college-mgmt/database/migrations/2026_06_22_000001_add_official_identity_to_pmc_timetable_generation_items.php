<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_pmc_timetable_generation_items', function (Blueprint $table) {
            $table->foreignId('timetable_version_id')->nullable()->after('generation_run_id')->constrained('timetable_versions')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('course_group_id')->constrained('programs')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->after('program_id')->constrained('batches')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->after('batch_id')->constrained('terms')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->after('term_id')->constrained('subjects')->nullOnDelete();
            $table->string('official_status', 80)->default('draft')->after('status')->index();
            $table->string('source_type', 80)->default('generated')->after('official_status')->index();
            $table->timestamp('published_at')->nullable()->after('source_type')->index();
            $table->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();

            $table->index(['timetable_version_id', 'official_status'], 'pmc_tt_item_official_version_status_idx');
            $table->index(['program_id', 'batch_id', 'term_id'], 'pmc_tt_item_scope_idx');
            $table->index(['day_of_week', 'timetable_slot_id', 'official_status'], 'pmc_tt_item_slot_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('academic_pmc_timetable_generation_items', function (Blueprint $table) {
            $table->dropForeign(['timetable_version_id']);
            $table->dropForeign(['program_id']);
            $table->dropForeign(['batch_id']);
            $table->dropForeign(['term_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['published_by']);
            $table->dropIndex('pmc_tt_item_official_version_status_idx');
            $table->dropIndex('pmc_tt_item_scope_idx');
            $table->dropIndex('pmc_tt_item_slot_status_idx');
            $table->dropColumn([
                'timetable_version_id',
                'program_id',
                'batch_id',
                'term_id',
                'subject_id',
                'official_status',
                'source_type',
                'published_at',
                'published_by',
            ]);
        });
    }
};
