<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselling_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->enum('interaction_type', ['call', 'email', 'whatsapp', 'walk_in', 'other']);
            $table->enum('outcome', ['interested', 'not_interested', 'callback', 'enrolled', 'lost', 'follow_up']);
            $table->text('notes');
            $table->date('next_followup_date')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselling_logs');
    }
};
