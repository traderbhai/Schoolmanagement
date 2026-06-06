<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_payment_id')->nullable()->constrained('admission_payments')->nullOnDelete();
            $table->decimal('requested_amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'processed'])->default('pending');
            $table->enum('reason', ['withdrawal', 'rejection', 'excess_payment', 'other'])->default('withdrawal');
            $table->text('reason_detail')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->string('account_holder_name', 255)->nullable();
            $table->string('utr_number', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('applicant_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('refund_requests');
    }
};
