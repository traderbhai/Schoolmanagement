<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->timestamp('uploaded_at')->nullable()->after('verified_at');
            $table->unsignedInteger('version')->default(1)->after('uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_documents', function (Blueprint $table) {
            $table->dropColumn(['uploaded_at', 'version']);
        });
    }
};
