<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->unsignedTinyInteger('step_order')->default(1)->after('approver_role');
            $table->string('workflow_type')->nullable()->after('step_order');
            $table->unsignedSmallInteger('sla_hours')->default(48)->after('workflow_type');
            $table->timestamp('due_at')->nullable()->after('sla_hours');
            $table->timestamp('escalated_at')->nullable()->after('due_at');
            $table->string('escalated_to_role')->nullable()->after('escalated_at');
            $table->unsignedBigInteger('parent_approval_id')->nullable()->after('escalated_to_role');

            $table->index(['status', 'due_at']);
            $table->index('workflow_type');
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->dropColumn([
                'step_order', 'workflow_type', 'sla_hours',
                'due_at', 'escalated_at', 'escalated_to_role', 'parent_approval_id',
            ]);
        });
    }
};
