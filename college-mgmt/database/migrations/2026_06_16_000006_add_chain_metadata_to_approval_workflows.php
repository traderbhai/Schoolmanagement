<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('approval_workflows', 'workflow_type')) {
                $table->string('workflow_type')->nullable()->after('approver_role')->index();
            }
            if (! Schema::hasColumn('approval_workflows', 'step_order')) {
                $table->unsignedInteger('step_order')->default(1)->after('workflow_type')->index();
            }
            if (! Schema::hasColumn('approval_workflows', 'sla_hours')) {
                $table->unsignedInteger('sla_hours')->nullable()->after('sla_days');
            }
            if (! Schema::hasColumn('approval_workflows', 'parent_approval_id')) {
                $table->unsignedBigInteger('parent_approval_id')->nullable()->after('escalated_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('approval_workflows', function (Blueprint $table) {
            foreach (['parent_approval_id', 'sla_hours', 'step_order', 'workflow_type'] as $column) {
                if (Schema::hasColumn('approval_workflows', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
