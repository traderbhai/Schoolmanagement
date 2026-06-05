<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_form_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->json('form_sections');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('program_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_form_configs');
    }
};
