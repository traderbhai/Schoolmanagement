<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('term_id')->constrained()->nullOnDelete();
            $table->enum('status', ['draft','published','archived'])->default('published')->after('is_active');
            $table->unsignedBigInteger('timetable_version_id')->nullable()->after('status');
        });
    }
    public function down(): void {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn(['batch_id','status','timetable_version_id']);
        });
    }
};
