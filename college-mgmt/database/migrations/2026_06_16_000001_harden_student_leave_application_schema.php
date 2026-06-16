<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            if (Schema::hasColumn('leave_applications', 'teacher_id')) {
                $table->foreignId('teacher_id')->nullable()->change();
            }

            if (! Schema::hasColumn('leave_applications', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            if (Schema::hasColumn('leave_applications', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }

            if (Schema::hasColumn('leave_applications', 'teacher_id')) {
                $table->foreignId('teacher_id')->nullable(false)->change();
            }
        });
    }
};
