<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_condonations', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_condonations', 'sessions_requested')) {
                $table->unsignedInteger('sessions_requested')->default(0)->after('reason');
            }

            if (! Schema::hasColumn('attendance_condonations', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('remarks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_condonations', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_condonations', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('attendance_condonations', 'sessions_requested')) {
                $table->dropColumn('sessions_requested');
            }
        });
    }
};
