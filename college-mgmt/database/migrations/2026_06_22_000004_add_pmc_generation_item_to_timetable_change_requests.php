<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_pmc_timetable_change_requests', function (Blueprint $table) {
            $table->foreignId('pmc_generation_item_id')
                ->nullable()
                ->after('timetable_version_id')
                ->constrained('academic_pmc_timetable_generation_items')
                ->nullOnDelete();

            $table->index(['pmc_generation_item_id', 'status'], 'pmc_tt_change_item_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('academic_pmc_timetable_change_requests', function (Blueprint $table) {
            $table->dropForeign(['pmc_generation_item_id']);
            $table->dropIndex('pmc_tt_change_item_status_idx');
            $table->dropColumn('pmc_generation_item_id');
        });
    }
};
