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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->foreignId('program_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('source', ['web_form', 'referral', 'advertisement', 'social_media', 'event', 'agent', 'other'])->default('web_form');
            $table->enum('status', ['new', 'contacted', 'interested', 'not_interested', 'converted'])->default('new');
            $table->text('notes')->nullable();
            $table->datetime('last_contacted_at')->nullable();
            $table->foreignId('converted_applicant_id')->nullable()->constrained('applicants')->onDelete('set null');
            $table->datetime('converted_at')->nullable();
            $table->timestamps();
            $table->index('email');
            $table->index('status');
            $table->index('source');
            $table->index('program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
