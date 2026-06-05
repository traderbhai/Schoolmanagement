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
        Schema::create('program_seat_matrix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->unsignedInteger('general_seats')->default(0);
            $table->unsignedInteger('obc_seats')->default(0);
            $table->unsignedInteger('obc_nc_seats')->default(0);
            $table->unsignedInteger('sc_seats')->default(0);
            $table->unsignedInteger('st_seats')->default(0);
            $table->unsignedInteger('ews_seats')->default(0);
            $table->unsignedInteger('pwd_seats')->default(0);
            $table->unsignedInteger('nri_seats')->default(0);
            $table->unsignedInteger('management_quota_seats')->default(0);
            $table->unsignedInteger('total_seats')->default(0);
            $table->decimal('state_quota_percentage', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['program_id', 'batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_seat_matrix');
    }
};
