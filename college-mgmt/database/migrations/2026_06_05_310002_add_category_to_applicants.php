<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->enum('category', ['general','obc','obc_nc','sc','st','ews','pwd','nri','management_quota'])->default('general')->after('batch_id');
            $table->boolean('category_certificate_verified')->default(false)->after('category');
            $table->decimal('pwd_percentage', 5, 2)->nullable()->after('category_certificate_verified');
            $table->string('domicile_state', 100)->nullable()->after('pwd_percentage');
            $table->boolean('is_state_quota')->default(false)->after('domicile_state');
            $table->enum('entrance_exam_type', ['cat','mat','xat','cmat','atma','gmat','other','none'])->nullable()->after('is_state_quota');
            $table->decimal('entrance_exam_score', 8, 2)->nullable()->after('entrance_exam_type');
            $table->string('entrance_exam_roll_number', 100)->nullable()->after('entrance_exam_score');
            $table->year('entrance_exam_year')->nullable()->after('entrance_exam_roll_number');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'category','category_certificate_verified','pwd_percentage',
                'domicile_state','is_state_quota','entrance_exam_type',
                'entrance_exam_score','entrance_exam_roll_number','entrance_exam_year',
            ]);
        });
    }
};
