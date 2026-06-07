<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_demands', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_demands', 'penalty_amount')) {
                $table->decimal('penalty_amount', 10, 2)->default(0)->after('final_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_demands', function (Blueprint $table) {
            $table->dropColumnIfExists('penalty_amount');
        });
    }
};
