<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('pmc_generation_item_id')
                ->nullable()
                ->after('timetable_version_id')
                ->constrained('academic_pmc_timetable_generation_items')
                ->nullOnDelete();
            $table->index('pmc_generation_item_id', 'timetable_entries_pmc_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropForeign(['pmc_generation_item_id']);
            $table->dropIndex('timetable_entries_pmc_item_idx');
            $table->dropColumn('pmc_generation_item_id');
        });
    }
};
