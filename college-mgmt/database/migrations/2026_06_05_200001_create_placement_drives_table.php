<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('placement_drives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('job_role');
            $table->string('package')->nullable();
            $table->decimal('min_cgpa', 4, 2)->nullable();
            $table->string('eligibility')->nullable();
            $table->date('drive_date')->nullable();
            $table->date('last_apply_date')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled'])->default('upcoming');
            $table->integer('vacancies')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_drives');
    }
};
