<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'owner_user_id' => fn () => $table->foreignId('owner_user_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete(),
                'current_handler_user_id' => fn () => $table->foreignId('current_handler_user_id')->nullable()->after('owner_user_id')->constrained('users')->nullOnDelete(),
                'assigned_by' => fn () => $table->foreignId('assigned_by')->nullable()->after('current_handler_user_id')->constrained('users')->nullOnDelete(),
                'assignment_reason' => fn () => $table->string('assignment_reason')->nullable()->after('assigned_by'),
                'assignment_mode' => fn () => $table->string('assignment_mode', 40)->nullable()->after('assignment_reason')->index(),
                'last_activity_at' => fn () => $table->timestamp('last_activity_at')->nullable()->after('assignment_mode')->index(),
                'escalated_to' => fn () => $table->foreignId('escalated_to')->nullable()->after('last_activity_at')->constrained('users')->nullOnDelete(),
                'escalated_at' => fn () => $table->timestamp('escalated_at')->nullable()->after('escalated_to')->index(),
                'sla_paused_until' => fn () => $table->timestamp('sla_paused_until')->nullable()->after('escalated_at'),
                'sla_pause_reason' => fn () => $table->string('sla_pause_reason')->nullable()->after('sla_paused_until'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('leads', $column)) {
                    $definition();
                }
            }
        });

        Schema::table('applicants', function (Blueprint $table) {
            foreach ([
                'owner_user_id' => fn () => $table->foreignId('owner_user_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete(),
                'current_handler_user_id' => fn () => $table->foreignId('current_handler_user_id')->nullable()->after('owner_user_id')->constrained('users')->nullOnDelete(),
                'assigned_by' => fn () => $table->foreignId('assigned_by')->nullable()->after('current_handler_user_id')->constrained('users')->nullOnDelete(),
                'assignment_reason' => fn () => $table->string('assignment_reason')->nullable()->after('assigned_by'),
                'assignment_mode' => fn () => $table->string('assignment_mode', 40)->nullable()->after('assignment_reason')->index(),
                'last_activity_at' => fn () => $table->timestamp('last_activity_at')->nullable()->after('assignment_mode')->index(),
                'escalated_to' => fn () => $table->foreignId('escalated_to')->nullable()->after('last_activity_at')->constrained('users')->nullOnDelete(),
                'escalated_at' => fn () => $table->timestamp('escalated_at')->nullable()->after('escalated_to')->index(),
                'sla_paused_until' => fn () => $table->timestamp('sla_paused_until')->nullable()->after('escalated_at'),
                'sla_pause_reason' => fn () => $table->string('sla_pause_reason')->nullable()->after('sla_paused_until'),
            ] as $column => $definition) {
                if (!Schema::hasColumn('applicants', $column)) {
                    $definition();
                }
            }
        });

        Schema::create('admission_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 40)->index();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('sla_before')->nullable();
            $table->timestamp('sla_after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['to_user_id', 'mode']);
            $table->index(['assigned_by', 'created_at']);
        });

        Schema::create('admission_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('object_type', 20)->default('lead')->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('conditions')->nullable();
            $table->string('assignee_strategy', 40)->default('round_robin');
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_team_id')->nullable()->constrained('department_teams')->nullOnDelete();
            $table->foreignId('target_role_id')->nullable()->constrained('department_roles')->nullOnDelete();
            $table->string('fallback_strategy', 40)->default('least_workload');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('admission_workflow_configs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('key', 120);
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true)->index();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['type', 'key']);
        });

        Schema::create('admission_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('secondary');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('admission_taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->foreignId('tagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['admission_tag_id', 'taggable_type', 'taggable_id'], 'admission_tag_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_taggables');
        Schema::dropIfExists('admission_tags');
        Schema::dropIfExists('admission_workflow_configs');
        Schema::dropIfExists('admission_assignment_rules');
        Schema::dropIfExists('admission_assignment_events');
    }
};
