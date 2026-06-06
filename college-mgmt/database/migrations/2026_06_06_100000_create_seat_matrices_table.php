<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('total_seats')->default(60);
            $table->unsignedSmallInteger('general_seats')->default(0);
            $table->unsignedSmallInteger('obc_seats')->default(0);
            $table->unsignedSmallInteger('sc_seats')->default(0);
            $table->unsignedSmallInteger('st_seats')->default(0);
            $table->unsignedSmallInteger('ews_seats')->default(0);
            $table->unsignedSmallInteger('management_quota')->default(0);
            $table->unsignedSmallInteger('nri_quota')->default(0);
            $table->unsignedSmallInteger('defence_quota')->default(0);
            $table->timestamps();
            $table->unique(['program_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_matrices');
    }
};
