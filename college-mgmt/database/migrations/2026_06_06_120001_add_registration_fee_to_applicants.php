<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('applicants', function (Blueprint $table) {
            $table->decimal('registration_fee_amount', 10, 2)->nullable()->after('entrance_exam_date');
            $table->timestamp('registration_fee_paid_at')->nullable()->after('registration_fee_amount');
            $table->string('registration_fee_receipt', 100)->nullable()->after('registration_fee_paid_at');
        });
    }
    public function down(): void {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn(['registration_fee_amount','registration_fee_paid_at','registration_fee_receipt']);
        });
    }
};
