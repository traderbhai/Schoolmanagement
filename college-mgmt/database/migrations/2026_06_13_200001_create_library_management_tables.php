<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn', 20)->unique()->nullable();
            $table->string('title', 300);
            $table->string('author', 200);
            $table->string('publisher', 200)->nullable();
            $table->string('edition', 50)->nullable();
            $table->smallInteger('year_of_publication')->nullable();
            $table->string('category', 100)->nullable();
            $table->json('subject_tags')->nullable();
            $table->string('language', 50)->default('English');
            $table->smallInteger('total_copies')->default(1);
            $table->smallInteger('available_copies')->default(1);
            $table->string('location', 100)->nullable();
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('accession_number', 30)->unique();
            $table->enum('condition_status', ['good','fair','damaged','lost'])->default('good');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('book_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_copy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('issued_at');
            $table->date('due_date');
            $table->datetime('returned_at')->nullable();
            $table->foreignId('return_accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('fine_amount', 6, 2)->default(0);
            $table->boolean('fine_paid')->default(false);
            $table->enum('status', ['issued','returned','overdue','lost'])->default('issued');
            $table->timestamps();
        });

        Schema::create('library_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('member_type', ['student','teacher','staff'])->default('student');
            $table->tinyInteger('max_books_allowed')->default(2);
            $table->smallInteger('max_days_allowed')->default(14);
            $table->decimal('fine_per_day', 4, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        Schema::create('library_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->datetime('reserved_at');
            $table->date('expires_at');
            $table->enum('status', ['pending','fulfilled','cancelled','expired'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('library_reservations');
        Schema::dropIfExists('library_memberships');
        Schema::dropIfExists('book_issues');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('books');
    }
};
