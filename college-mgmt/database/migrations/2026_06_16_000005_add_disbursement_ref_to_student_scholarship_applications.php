<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_scholarship_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('student_scholarship_applications', 'disbursement_ref')) {
                $table->string('disbursement_ref')->nullable()->unique()->after('disbursed_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_scholarship_applications', function (Blueprint $table) {
            if (Schema::hasColumn('student_scholarship_applications', 'disbursement_ref')) {
                $table->dropUnique(['disbursement_ref']);
                $table->dropColumn('disbursement_ref');
            }
        });
    }
};
