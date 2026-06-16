<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_schemes', function (Blueprint $table) {
            if (! Schema::hasColumn('scholarship_schemes', 'min_cgpa')) {
                $table->decimal('min_cgpa', 4, 2)->nullable()->after('criteria');
            }
            if (! Schema::hasColumn('scholarship_schemes', 'max_family_income')) {
                $table->decimal('max_family_income', 12, 2)->nullable()->after('min_cgpa');
            }
            if (! Schema::hasColumn('scholarship_schemes', 'requires_document')) {
                $table->boolean('requires_document')->default(false)->after('max_family_income');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_schemes', function (Blueprint $table) {
            if (Schema::hasColumn('scholarship_schemes', 'requires_document')) {
                $table->dropColumn('requires_document');
            }
            if (Schema::hasColumn('scholarship_schemes', 'max_family_income')) {
                $table->dropColumn('max_family_income');
            }
            if (Schema::hasColumn('scholarship_schemes', 'min_cgpa')) {
                $table->dropColumn('min_cgpa');
            }
        });
    }
};
