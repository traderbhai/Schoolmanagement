<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('term_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('current_term_id')->constrained('terms')->onDelete('cascade');
            $table->foreignId('promoted_to_term_id')->constrained('terms')->onDelete('cascade');
            $table->decimal('cgpa', 3, 2)->nullable();
            $table->integer('attendance_percentage')->nullable();
            $table->boolean('meets_academic_criteria')->default(false);
            $table->boolean('meets_attendance_criteria')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'on_hold'])->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('processed_at')->nullable();
            $table->timestamps();
            $table->index('student_id');
            $table->index('status');
        });

        // Create notifications table for B4
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->string('action_url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->datetime('read_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('is_read');
        });

        // Create scholarships table for B3
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('percentage', 5, 2);
            $table->decimal('fixed_amount', 10, 2)->nullable();
            $table->enum('type', ['merit', 'need_based', 'category', 'other'])->default('merit');
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index('student_id');
            $table->index('status');
        });

        // Create fee_demands table for B3
        Schema::create('fee_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('term_id')->constrained()->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('scholarship_deduction', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->date('due_date');
            $table->date('last_reminder_sent')->nullable();
            $table->enum('status', ['pending', 'partially_paid', 'fully_paid', 'overdue'])->default('pending');
            $table->timestamps();
            $table->index('student_id');
            $table->index('status');
        });

        // Create academic_calendar table for B5
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->onDelete('cascade');
            $table->date('event_date');
            $table->string('event_name');
            $table->text('description')->nullable();
            $table->enum('event_type', ['holiday', 'exam_week', 'semester_start', 'semester_end', 'class_start', 'class_end', 'other'])->default('other');
            $table->boolean('is_holiday')->default(false);
            $table->timestamps();
            $table->index('term_id');
            $table->index('event_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('term_promotions');
    }
};
