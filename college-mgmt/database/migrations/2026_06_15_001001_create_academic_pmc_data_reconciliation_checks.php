<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_pmc_data_reconciliation_checks', function (Blueprint $table) {
            $table->id();
            $table->string('check_key', 120)->index();
            $table->string('check_group', 80)->index();
            $table->string('status', 40)->default('ok')->index();
            $table->string('severity', 40)->default('low')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('actual_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->string('source_type', 120)->nullable()->index();
            $table->string('source_key')->nullable();
            $table->string('recommended_action')->nullable();
            $table->json('details')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['check_key', 'source_type', 'source_key'], 'pmc_recon_unique_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_pmc_data_reconciliation_checks');
    }
};
