<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_dean_review_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('review_type', 80)->index();
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->foreignId('chaired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_type', 40)->default('department')->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->string('status', 40)->default('scheduled')->index();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('academic_dean_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained('academic_dean_review_meetings')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('source_type', 80)->default('manual')->index();
            $table->string('source_key')->nullable()->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 30)->default('normal')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->string('status', 40)->default('open')->index();
            $table->text('closure_note')->nullable();
            $table->timestamp('closed_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'status']);
            $table->index(['owner_user_id', 'status']);
        });

        Schema::create('academic_dean_saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('surface', 80)->index();
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('academic_dean_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_key', 100)->index();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('exported_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_dean_export_logs');
        Schema::dropIfExists('academic_dean_saved_views');
        Schema::dropIfExists('academic_dean_action_items');
        Schema::dropIfExists('academic_dean_review_meetings');
    }
};
