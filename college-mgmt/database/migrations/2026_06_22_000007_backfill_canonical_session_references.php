<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('attendances', 'pmc_generation_item_id')) {
            DB::statement('
                UPDATE attendances
                SET pmc_generation_item_id = (
                    SELECT timetable_entries.pmc_generation_item_id
                    FROM timetable_entries
                    WHERE timetable_entries.id = attendances.timetable_entry_id
                    LIMIT 1
                )
                WHERE attendances.pmc_generation_item_id IS NULL
                  AND EXISTS (
                    SELECT 1
                    FROM timetable_entries
                    WHERE timetable_entries.id = attendances.timetable_entry_id
                      AND timetable_entries.pmc_generation_item_id IS NOT NULL
                  )
            ');
        }

        if (Schema::hasColumn('timetable_substitutions', 'pmc_generation_item_id')) {
            DB::statement('
                UPDATE timetable_substitutions
                SET pmc_generation_item_id = (
                    SELECT timetable_entries.pmc_generation_item_id
                    FROM timetable_entries
                    WHERE timetable_entries.id = timetable_substitutions.timetable_entry_id
                    LIMIT 1
                )
                WHERE timetable_substitutions.pmc_generation_item_id IS NULL
                  AND EXISTS (
                    SELECT 1
                    FROM timetable_entries
                    WHERE timetable_entries.id = timetable_substitutions.timetable_entry_id
                      AND timetable_entries.pmc_generation_item_id IS NOT NULL
                  )
            ');
        }
    }

    public function down(): void
    {
        // Backfill is intentionally not reversed.
    }
};
