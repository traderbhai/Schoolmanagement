<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('book_issues', 'fine_paid_at')) {
                $table->timestamp('fine_paid_at')->nullable()->after('fine_paid');
            }

            if (! Schema::hasColumn('book_issues', 'fine_collected_by')) {
                $table->foreignId('fine_collected_by')->nullable()->after('fine_paid_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            if (Schema::hasColumn('book_issues', 'fine_collected_by')) {
                $table->dropConstrainedForeignId('fine_collected_by');
            }

            if (Schema::hasColumn('book_issues', 'fine_paid_at')) {
                $table->dropColumn('fine_paid_at');
            }
        });
    }
};
