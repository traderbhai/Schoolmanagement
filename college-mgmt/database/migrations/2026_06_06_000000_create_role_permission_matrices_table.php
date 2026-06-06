<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permission_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->unsignedBigInteger('program_id')->nullable();
            $table->boolean('program_specific')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'permission_id', 'program_id']);
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_matrices');
    }
};
