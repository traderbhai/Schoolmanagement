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
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('room_number', 20)->unique();
            $table->integer('capacity')->default(60);
            $table->enum('type', ['lecture', 'lab', 'seminar', 'auditorium'])->default('lecture');
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->boolean('has_projector')->default(false);
            $table->boolean('has_lab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
