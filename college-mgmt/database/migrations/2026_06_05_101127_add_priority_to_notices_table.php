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
        Schema::table('notices', function (Blueprint $table) {
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal')->after('audience');
            $table->timestamp('published_at')->nullable()->after('publish_date');
            $table->timestamp('expires_at')->nullable()->after('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['priority', 'published_at', 'expires_at']);
        });
    }
};
