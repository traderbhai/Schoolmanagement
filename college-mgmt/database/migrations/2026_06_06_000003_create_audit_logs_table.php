<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->onDelete('restrict');
            $table->string('action'); // 'role_assigned', 'role_revoked', 'permission_changed'
            $table->string('target_type'); // Model class name
            $table->unsignedBigInteger('target_id');
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'actor_id']);
            $table->index('action');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
