<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_feature_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->string('feature_code'); // e.g., 'exam.enter_marks', 'admission.approve'
            $table->enum('access_level', ['view', 'create', 'edit', 'approve', 'delete']);
            $table->timestamps();

            $table->unique(['role_id', 'feature_code']);
            $table->index('feature_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_feature_access');
    }
};
