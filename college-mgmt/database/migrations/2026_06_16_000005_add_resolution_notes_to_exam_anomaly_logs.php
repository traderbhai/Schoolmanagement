<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_anomaly_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_anomaly_logs', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable()->after('action_taken');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_anomaly_logs', function (Blueprint $table) {
            if (Schema::hasColumn('exam_anomaly_logs', 'resolution_notes')) {
                $table->dropColumn('resolution_notes');
            }
        });
    }
};
