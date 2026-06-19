<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_calendars', function (Blueprint $table) {
            if (! Schema::hasColumn('academic_calendars', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('academic_calendars', function (Blueprint $table) {
            if (Schema::hasColumn('academic_calendars', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
