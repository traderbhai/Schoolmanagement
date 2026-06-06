<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->unsignedBigInteger('program_id')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict');
            $table->date('active_until')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'program_id']);
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->index(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
