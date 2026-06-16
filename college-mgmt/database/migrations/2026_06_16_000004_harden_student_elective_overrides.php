<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('student_subject_enrollments', 'previous_subject_id')) {
                $table->foreignId('previous_subject_id')->nullable()->after('subject_id')->constrained('subjects')->nullOnDelete();
            }

            if (! Schema::hasColumn('student_subject_enrollments', 'override_reason')) {
                $table->text('override_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('student_subject_enrollments', 'overridden_by')) {
                $table->foreignId('overridden_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('student_subject_enrollments', 'overridden_at')) {
                $table->timestamp('overridden_at')->nullable()->after('overridden_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_subject_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('student_subject_enrollments', 'overridden_at')) {
                $table->dropColumn('overridden_at');
            }

            if (Schema::hasColumn('student_subject_enrollments', 'overridden_by')) {
                $table->dropConstrainedForeignId('overridden_by');
            }

            if (Schema::hasColumn('student_subject_enrollments', 'override_reason')) {
                $table->dropColumn('override_reason');
            }

            if (Schema::hasColumn('student_subject_enrollments', 'previous_subject_id')) {
                $table->dropConstrainedForeignId('previous_subject_id');
            }
        });
    }
};
