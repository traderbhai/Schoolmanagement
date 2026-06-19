<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_scope_assignments', function (Blueprint $table) {
            $table->foreignId('deactivated_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable()->after('deactivated_by');
        });
    }

    public function down(): void
    {
        Schema::table('academic_scope_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
            $table->dropColumn('deactivated_at');
        });
    }
};
