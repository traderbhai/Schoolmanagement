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
        Schema::create('offer_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->onDelete('cascade');
            $table->foreignId('program_id')->constrained()->onDelete('cascade');
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->string('offer_number')->unique();
            $table->enum('status', ['issued', 'accepted', 'declined', 'expired'])->default('issued');
            $table->date('acceptance_deadline');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('declined_reason')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->foreignId('issued_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->index('status');
            $table->index('applicant_id');
            $table->index('program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_letters');
    }
};
