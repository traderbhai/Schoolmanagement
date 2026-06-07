<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('headline')->nullable();
            $table->text('objective')->nullable();
            $table->json('skills')->nullable();
            $table->json('languages')->nullable();
            $table->json('projects')->nullable();        // [{title, desc, tech, url}]
            $table->json('certifications')->nullable(); // [{name, issuer, year}]
            $table->json('experience')->nullable();     // [{company, role, from, to, desc}]
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->boolean('is_complete')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('student_resumes'); }
};
