<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('applicants', function (Blueprint $table) {
            $table->enum('category', ['general','obc','obc_ncl','sc','st','ews','pwd','nri'])->nullable()->after('notes');
            $table->string('sub_category', 100)->nullable()->after('category');
            $table->boolean('is_pwd')->default(false)->after('sub_category');
            $table->string('pwd_certificate_number', 100)->nullable()->after('is_pwd');
            $table->string('entrance_exam_name', 255)->nullable()->after('pwd_certificate_number');
            $table->string('entrance_exam_roll_number', 100)->nullable()->after('entrance_exam_name');
            $table->decimal('entrance_exam_score', 8, 2)->nullable()->after('entrance_exam_roll_number');
            $table->integer('entrance_exam_rank')->nullable()->after('entrance_exam_score');
            $table->date('entrance_exam_date')->nullable()->after('entrance_exam_rank');
        });
    }
    public function down(): void {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['category','sub_category','is_pwd','pwd_certificate_number',
                'entrance_exam_name','entrance_exam_roll_number','entrance_exam_score',
                'entrance_exam_rank','entrance_exam_date']);
        });
    }
};
