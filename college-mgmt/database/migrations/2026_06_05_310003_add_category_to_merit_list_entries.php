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
        Schema::table('merit_list_entries', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('notes');
            $table->unsignedInteger('category_rank')->nullable()->after('category');
            $table->enum('quota_type', ['open','category','pwd','nri','management'])->nullable()->after('category_rank');
            $table->boolean('is_supernumerary')->default(false)->after('quota_type');
        });
    }

    public function down(): void
    {
        Schema::table('merit_list_entries', function (Blueprint $table) {
            $table->dropColumn(['category','category_rank','quota_type','is_supernumerary']);
        });
    }
};
