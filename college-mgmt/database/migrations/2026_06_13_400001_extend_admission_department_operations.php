<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'priority')) {
                $table->string('priority', 20)->default('normal')->after('status')->index();
            }
            if (!Schema::hasColumn('leads', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('assigned_at')->index();
            }
            if (!Schema::hasColumn('leads', 'next_action')) {
                $table->string('next_action')->nullable()->after('sla_due_at');
            }
            if (!Schema::hasColumn('leads', 'team')) {
                $table->string('team')->nullable()->after('next_action')->index();
            }
            if (!Schema::hasColumn('leads', 'region')) {
                $table->string('region')->nullable()->after('team')->index();
            }
        });

        Schema::table('applicants', function (Blueprint $table) {
            if (!Schema::hasColumn('applicants', 'assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('applicants', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('applicants', 'priority')) {
                $table->string('priority', 20)->default('normal')->after('status')->index();
            }
            if (!Schema::hasColumn('applicants', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('assigned_at')->index();
            }
            if (!Schema::hasColumn('applicants', 'next_action')) {
                $table->string('next_action')->nullable()->after('sla_due_at');
            }
        });

        Schema::table('admission_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('admission_payments', 'provider')) {
                $table->string('provider')->nullable()->after('payment_mode')->index();
            }
            if (!Schema::hasColumn('admission_payments', 'gateway_order_id')) {
                $table->string('gateway_order_id')->nullable()->after('provider')->index();
            }
            if (!Schema::hasColumn('admission_payments', 'gateway_payment_id')) {
                $table->string('gateway_payment_id')->nullable()->after('gateway_order_id')->index();
            }
            if (!Schema::hasColumn('admission_payments', 'gateway_status')) {
                $table->string('gateway_status')->nullable()->after('gateway_payment_id')->index();
            }
            if (!Schema::hasColumn('admission_payments', 'gateway_payload')) {
                $table->json('gateway_payload')->nullable()->after('gateway_status');
            }
            if (!Schema::hasColumn('admission_payments', 'paid_via_gateway_at')) {
                $table->timestamp('paid_via_gateway_at')->nullable()->after('gateway_payload');
            }
        });

        Schema::create('admission_process_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->index(['program_id', 'batch_id', 'is_active']);
        });

        Schema::create('admission_process_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_process_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('stage_key')->index();
            $table->unsignedInteger('sequence')->default(1);
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['admission_process_template_id', 'stage_key'], 'admission_process_stage_unique');
        });

        Schema::create('admission_payment_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('razorpay_mock')->index();
            $table->string('event_id')->nullable();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id'], 'admission_gateway_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_payment_gateway_events');
        Schema::dropIfExists('admission_process_stages');
        Schema::dropIfExists('admission_process_templates');
    }
};
