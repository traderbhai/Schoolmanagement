<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('study_materials', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('subject_announcements', function (Blueprint $table) {
            if (! Schema::hasColumn('subject_announcements', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('study_materials', function (Blueprint $table) {
            if (Schema::hasColumn('study_materials', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('subject_announcements', function (Blueprint $table) {
            if (Schema::hasColumn('subject_announcements', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
