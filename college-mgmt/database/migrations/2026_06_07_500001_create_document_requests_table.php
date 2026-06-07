<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('document_type'); // bonafide/fee_letter/character/migration/noc/id_card
            $table->string('purpose')->nullable();
            $table->text('additional_info')->nullable();
            $table->enum('status', ['pending','approved','rejected','ready'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fulfilled_at')->nullable();
            $table->string('output_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('document_requests'); }
};
