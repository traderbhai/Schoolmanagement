<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Student anti-ragging declaration (submitted once at admission/each year)
        Schema::create('anti_ragging_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('academic_year', 20); // e.g. 2025-26
            $table->boolean('student_signed')->default(false);
            $table->boolean('parent_signed')->default(false);
            $table->timestamp('student_signed_at')->nullable();
            $table->timestamp('parent_signed_at')->nullable();
            $table->string('student_ip', 45)->nullable();
            $table->string('parent_ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year']);
        });

        // Anti-ragging committee members
        Schema::create('anti_ragging_committee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 100); // chairman, member, student_rep, external_member
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Anti-ragging complaints
        Schema::create('anti_ragging_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete(); // null = anonymous
            $table->foreignId('victim_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('incident_date', 20)->nullable(); // stored as string for flexibility
            $table->string('incident_location', 200)->nullable();
            $table->text('description');
            $table->boolean('is_anonymous')->default(false);
            $table->enum('severity', ['minor','moderate','severe'])->default('moderate');
            $table->enum('status', ['received','under_investigation','action_taken','closed','false_complaint'])->default('received');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('investigation_notes')->nullable();
            $table->text('action_taken')->nullable();
            $table->timestamp('action_taken_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('ugc_reference')->nullable(); // if escalated to UGC
            $table->timestamps();
        });

        // Accused students in a complaint (many per complaint)
        Schema::create('anti_ragging_accused', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('anti_ragging_complaints')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accused_name', 200)->nullable(); // if student not in system
            $table->string('accused_class', 100)->nullable();
            $table->enum('status', ['under_investigation','punishment_given','acquitted'])->default('under_investigation');
            $table->text('punishment_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('anti_ragging_accused');
        Schema::dropIfExists('anti_ragging_complaints');
        Schema::dropIfExists('anti_ragging_committee');
        Schema::dropIfExists('anti_ragging_declarations');
    }
};
