<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('career_event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('career_event_registrations', 'status')) {
                $table->string('status', 30)->default('registered')->after('attended')->index();
            }
            if (! Schema::hasColumn('career_event_registrations', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('career_event_registrations', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('career_event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('career_event_registrations', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            if (Schema::hasColumn('career_event_registrations', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('career_event_registrations', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
