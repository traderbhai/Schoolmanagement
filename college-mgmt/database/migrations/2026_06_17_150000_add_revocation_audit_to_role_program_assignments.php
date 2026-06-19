<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_program_assignments', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable()->after('revoked_by');
        });
    }

    public function down(): void
    {
        Schema::table('role_program_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn('revoked_at');
        });
    }
};
