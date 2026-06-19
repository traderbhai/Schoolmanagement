<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_demands', function (Blueprint $table) {
            if (! Schema::hasColumn('fee_demands', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('last_reminder_sent');
            }
            if (! Schema::hasColumn('fee_demands', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('fee_demands', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('fee_demands', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_demands', function (Blueprint $table) {
            if (Schema::hasColumn('fee_demands', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('fee_demands', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }
            if (Schema::hasColumn('fee_demands', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            if (Schema::hasColumn('fee_demands', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
        });
    }
};
