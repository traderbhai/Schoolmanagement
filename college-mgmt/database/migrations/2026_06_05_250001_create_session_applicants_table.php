<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('selection_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->enum('attendance_status', ['pending', 'present', 'absent', 'excused'])->default('pending');
            $table->integer('panel_number')->nullable();
            $table->timestamps();

            $table->unique(['selection_session_id', 'applicant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_applicants');
    }
};
