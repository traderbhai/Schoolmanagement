<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostel_fee_demands', function (Blueprint $table) {
            if (! Schema::hasColumn('hostel_fee_demands', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('hostel_fee_demands', 'waived_at')) {
                $table->timestamp('waived_at')->nullable()->after('paid_by');
            }

            if (! Schema::hasColumn('hostel_fee_demands', 'waived_by')) {
                $table->foreignId('waived_by')->nullable()->after('waived_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('hostel_fee_demands', 'waiver_reason')) {
                $table->text('waiver_reason')->nullable()->after('waived_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hostel_fee_demands', function (Blueprint $table) {
            if (Schema::hasColumn('hostel_fee_demands', 'waiver_reason')) {
                $table->dropColumn('waiver_reason');
            }

            if (Schema::hasColumn('hostel_fee_demands', 'waived_by')) {
                $table->dropConstrainedForeignId('waived_by');
            }

            if (Schema::hasColumn('hostel_fee_demands', 'waived_at')) {
                $table->dropColumn('waived_at');
            }

            if (Schema::hasColumn('hostel_fee_demands', 'paid_by')) {
                $table->dropConstrainedForeignId('paid_by');
            }
        });
    }
};
