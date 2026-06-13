<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_evaluator_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('available_from');
            $table->dateTime('available_until');
            $table->string('availability_type')->default('available');
            $table->string('location_mode')->nullable();
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_assessment_schedule_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained('admission_assessment_panels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conflict_type');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->text('message');
            $table->dateTime('detected_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_counsellor_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('period_type')->default('daily');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('target_calls')->default(0);
            $table->unsignedInteger('target_followups')->default(0);
            $table->unsignedInteger('target_applications')->default(0);
            $table->unsignedInteger('target_enrollments')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_counsellor_coaching_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counsellor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_type')->default('daily_review');
            $table->string('score_band')->default('on_track');
            $table->text('strengths')->nullable();
            $table->text('improvement_areas')->nullable();
            $table->text('action_plan')->nullable();
            $table->date('reviewed_for_date')->nullable();
            $table->date('next_review_at')->nullable();
            $table->string('status')->default('open');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_route_access_audits', function (Blueprint $table) {
            $table->id();
            $table->string('route_name')->unique();
            $table->string('uri');
            $table->string('method')->default('GET');
            $table->string('required_scope')->nullable();
            $table->string('risk_level')->default('low');
            $table->string('status')->default('reviewed');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_route_access_audits');
        Schema::dropIfExists('admission_counsellor_coaching_notes');
        Schema::dropIfExists('admission_counsellor_targets');
        Schema::dropIfExists('admission_assessment_schedule_conflicts');
        Schema::dropIfExists('admission_evaluator_availabilities');
    }
};
