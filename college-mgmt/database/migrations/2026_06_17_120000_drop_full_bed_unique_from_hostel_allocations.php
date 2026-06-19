<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostel_allocations', function (Blueprint $table) {
            $table->dropUnique(['hostel_room_id', 'bed_number']);
        });

        Schema::table('hostel_allocations', function (Blueprint $table) {
            $table->index(['hostel_room_id', 'bed_number', 'status'], 'hostel_allocations_bed_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('hostel_allocations', function (Blueprint $table) {
            $table->dropIndex('hostel_allocations_bed_status_index');
            $table->unique(['hostel_room_id', 'bed_number']);
        });
    }
};
