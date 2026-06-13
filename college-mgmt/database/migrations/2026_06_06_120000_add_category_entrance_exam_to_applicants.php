<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('applicants', function (Blueprint $table) {
            // category, entrance_exam_score, entrance_exam_roll_number already added by earlier migration
            if (!Schema::hasColumn('applicants', 'sub_category')) {
                $table->string('sub_category', 100)->nullable()->after('category');
            }
            if (!Schema::hasColumn('applicants', 'is_pwd')) {
                $table->boolean('is_pwd')->default(false);
            }
            if (!Schema::hasColumn('applicants', 'pwd_certificate_number')) {
                $table->string('pwd_certificate_number', 100)->nullable();
            }
            if (!Schema::hasColumn('applicants', 'entrance_exam_name')) {
                $table->string('entrance_exam_name', 255)->nullable();
            }
            if (!Schema::hasColumn('applicants', 'entrance_exam_rank')) {
                $table->integer('entrance_exam_rank')->nullable();
            }
            if (!Schema::hasColumn('applicants', 'entrance_exam_date')) {
                $table->date('entrance_exam_date')->nullable();
            }
        });
    }
    public function down(): void {
        Schema::table('applicants', function (Blueprint $table) {
            $cols = ['sub_category','is_pwd','pwd_certificate_number',
                'entrance_exam_name','entrance_exam_rank','entrance_exam_date'];
            $existing = array_filter($cols, fn($c) => Schema::hasColumn('applicants', $c));
            if ($existing) $table->dropColumn(array_values($existing));
        });
    }
};
