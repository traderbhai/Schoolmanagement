<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_reporting_lines', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
            $table->foreignId('revoked_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('org_reporting_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['is_active', 'revoked_at']);
        });
    }
};
