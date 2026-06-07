<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->integer('graduation_year');
            $table->string('current_employer')->nullable();
            $table->string('current_role')->nullable();
            $table->decimal('current_salary', 12, 2)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('India');
            $table->text('feedback')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_profiles');
    }
};
