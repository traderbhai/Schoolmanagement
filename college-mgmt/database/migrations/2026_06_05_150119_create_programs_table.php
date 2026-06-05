<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('abbreviation', 10)->nullable();
            $table->enum('system_type', ['semester', 'trimester', 'annual', 'quarter'])->default('semester');
            $table->unsignedTinyInteger('duration_years')->default(2);
            $table->unsignedTinyInteger('total_terms')->default(4);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_intake_capacity')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
