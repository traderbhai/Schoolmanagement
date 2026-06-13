<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gender', 10)->default('mixed'); // boys/girls/mixed
            $table->tinyInteger('total_floors')->default(1);
            $table->foreignId('warden_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('address_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_block_id')->constrained()->cascadeOnDelete();
            $table->string('room_number', 20);
            $table->tinyInteger('floor')->default(0);
            $table->string('room_type', 20)->default('double'); // single/double/triple/dormitory
            $table->tinyInteger('capacity')->default(2);
            $table->decimal('monthly_fee', 8, 2)->default(0);
            $table->json('amenities')->nullable();
            $table->string('status', 20)->default('available'); // available/occupied/maintenance/reserved
            $table->timestamps();
        });

        Schema::create('hostel_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('bed_number')->default(1);
            $table->date('allocated_from');
            $table->date('allocated_to')->nullable();
            $table->string('status', 20)->default('active'); // active/vacated/transferred
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('vacated_at')->nullable();
            $table->string('vacate_reason')->nullable();
            $table->timestamps();
            // Note: partial unique (status=active) not supported in SQLite; enforce in app logic
            $table->unique(['hostel_room_id', 'bed_number']);
        });

        Schema::create('hostel_fee_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_allocation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->decimal('amount', 8, 2);
            $table->string('status', 20)->default('pending'); // pending/paid/waived
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['hostel_allocation_id', 'month']);
        });

        Schema::create('outpass_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_allocation_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->dateTime('out_datetime');
            $table->dateTime('expected_return');
            $table->dateTime('actual_return')->nullable();
            $table->string('status', 20)->default('pending'); // pending/approved/rejected/returned
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('hostel_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hostel_block_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category', 20)->default('other'); // maintenance/hygiene/food/security/ragging/other
            $table->string('priority', 10)->default('medium'); // low/medium/high
            $table->string('status', 20)->default('open'); // open/in_progress/resolved/closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_complaints');
        Schema::dropIfExists('outpass_requests');
        Schema::dropIfExists('hostel_fee_demands');
        Schema::dropIfExists('hostel_allocations');
        Schema::dropIfExists('hostel_rooms');
        Schema::dropIfExists('hostel_blocks');
    }
};
