<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scholarship_schemes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('program_id')->nullable(); // null = all programs
            $table->string('name');
            $table->string('scheme_code')->unique();
            $table->string('type'); // merit, need_based, government, aicte, institution
            $table->text('criteria')->nullable(); // plain-text eligibility criteria
            $table->decimal('max_amount', 10, 2)->default(0);
            $table->unsignedInteger('available_seats')->nullable(); // null = unlimited
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_schemes');
    }
};
