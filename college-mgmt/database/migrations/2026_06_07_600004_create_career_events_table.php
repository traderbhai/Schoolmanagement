<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('career_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('event_type', ['seminar','mock_interview','workshop','company_visit','career_fair','other']);
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->date('event_date');
            $table->string('venue')->nullable();
            $table->text('description')->nullable();
            $table->integer('seats')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
        Schema::create('career_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->boolean('attended')->default(false);
            $table->timestamps();
            $table->unique(['career_event_id','student_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('career_event_registrations');
        Schema::dropIfExists('career_events');
    }
};
