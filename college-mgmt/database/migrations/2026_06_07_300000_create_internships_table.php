<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('set null');
            $table->string('company_name');
            $table->string('role_title');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('type')->default('internship'); // internship, industrial_training, live_project
            $table->string('status')->default('ongoing'); // ongoing, completed, dropped
            $table->text('description')->nullable();
            $table->decimal('stipend', 10, 2)->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_email')->nullable();
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable(); // 1-5
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internships');
    }
};
