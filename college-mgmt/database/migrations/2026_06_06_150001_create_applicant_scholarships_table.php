<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('applicant_scholarships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_id');
            $table->unsignedBigInteger('scheme_id');
            $table->decimal('awarded_amount', 10, 2);
            $table->string('status')->default('awarded'); // awarded, disbursed, cancelled
            $table->unsignedBigInteger('awarded_by')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_ref')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');
            $table->foreign('scheme_id')->references('id')->on('scholarship_schemes')->onDelete('cascade');
            $table->foreign('awarded_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['applicant_id', 'scheme_id']); // one award per scheme per applicant
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_scholarships');
    }
};
