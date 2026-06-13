<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fee_installment_plans')) {
            Schema::create('fee_installment_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained()->cascadeOnDelete();
                $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name'); // e.g. "3-Installment Plan 2026"
                $table->integer('installments_count')->default(1);
                $table->decimal('late_fee_per_day', 8, 2)->default(0);
                $table->integer('grace_period_days')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('fee_installments')) {
            Schema::create('fee_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fee_demand_id')->constrained()->cascadeOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('fee_installment_plans')->nullOnDelete();
                $table->integer('installment_number');
                $table->decimal('amount', 10, 2);
                $table->date('due_date');
                $table->decimal('late_fee_accrued', 10, 2)->default(0);
                $table->decimal('amount_paid', 10, 2)->default(0);
                $table->enum('status', ['pending', 'paid', 'overdue', 'waived'])->default('pending');
                $table->date('paid_at')->nullable();
                $table->timestamps();
            });
        }

        // Add late_fee_rate to fee_demands if missing
        Schema::table('fee_demands', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_demands', 'late_fee_rate')) {
                $table->decimal('late_fee_rate', 8, 2)->default(0)->comment('Per day late fee rate');
            }
            if (!Schema::hasColumn('fee_demands', 'grace_period_days')) {
                $table->integer('grace_period_days')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_installments');
        Schema::dropIfExists('fee_installment_plans');
        Schema::table('fee_demands', function (Blueprint $table) {
            if (Schema::hasColumn('fee_demands', 'late_fee_rate')) $table->dropColumn('late_fee_rate');
            if (Schema::hasColumn('fee_demands', 'grace_period_days')) $table->dropColumn('grace_period_days');
        });
    }
};
