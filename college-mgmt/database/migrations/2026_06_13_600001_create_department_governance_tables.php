<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_feature_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('feature_name');
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('config')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'feature_key'], 'department_feature_unique');
        });

        Schema::create('department_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'created_at']);
        });

        Schema::create('department_impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['department_id', 'actor_user_id']);
            $table->index(['target_user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_impersonation_sessions');
        Schema::dropIfExists('department_activity_logs');
        Schema::dropIfExists('department_feature_settings');
    }
};
