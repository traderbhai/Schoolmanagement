<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_applications', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_remarks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            if (Schema::hasColumn('leave_applications', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
        });
    }
};
