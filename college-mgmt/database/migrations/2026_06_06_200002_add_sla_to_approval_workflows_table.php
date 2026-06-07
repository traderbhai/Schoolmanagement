<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->integer('sla_days')->default(3)->after('remarks');
            $table->timestamp('due_at')->nullable()->after('sla_days');
            $table->string('escalated_to_role')->nullable()->after('due_at');
            $table->timestamp('escalated_at')->nullable()->after('escalated_to_role');
        });
    }
    public function down(): void {
        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->dropColumn(['sla_days','due_at','escalated_to_role','escalated_at']);
        });
    }
};
