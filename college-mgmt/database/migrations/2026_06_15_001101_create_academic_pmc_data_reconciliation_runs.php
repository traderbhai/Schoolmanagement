<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_data_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40)->default('manual')->index();
            $table->string('status', 40)->default('running')->index();
            $table->boolean('repair_requested')->default(false)->index();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->unsignedInteger('checks_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('repaired_count')->default(0);
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_data_reconciliation_runs');
    }
};
