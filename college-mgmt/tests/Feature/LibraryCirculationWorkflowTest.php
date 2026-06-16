<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\LibraryMembership;
use App\Models\LibraryReservation;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LibraryFineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LibraryCirculationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(): Student
    {
        $user = $this->userWithRole('student');

        return Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    private function bookWithCopy(array $bookOverrides = []): array
    {
        $book = Book::create(array_merge([
            'title' => 'Database Systems',
            'author' => 'Ramakrishnan',
            'isbn' => 'ISBN-' . uniqid(),
            'category' => 'Computer Science',
            'total_copies' => 1,
            'available_copies' => 1,
            'is_active' => true,
        ], $bookOverrides));

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'ACC-' . uniqid(),
            'is_available' => true,
        ]);

        return [$book, $copy];
    }

    public function test_admin_library_dashboard_catalog_and_book_detail_render(): void
    {
        [$book] = $this->bookWithCopy();

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.library.index'))
            ->assertStatus(200)
            ->assertSee('Library Management')
            ->assertSee('Total Books');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.library.books', ['search' => 'Database']))
            ->assertStatus(200)
            ->assertSee('Database Systems')
            ->assertSee('Computer Science');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.library.books.show', $book))
            ->assertStatus(200)
            ->assertSee('Copies')
            ->assertSee('Issue History');
    }

    public function test_admin_can_issue_and_return_book_copy_for_student(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $copy->id,
                'borrower_type' => 'student',
                'borrower_id' => $student->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Book issued successfully.');

        $issue = BookIssue::first();
        $this->assertNotNull($issue);
        $this->assertSame('issued', $issue->status);
        $this->assertFalse($copy->fresh()->is_available);
        $this->assertSame(0, $book->fresh()->available_copies);

        $issue->update([
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'overdue',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.issues.return', $issue))
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame('returned', $issue->status);
        $this->assertNotNull($issue->returned_at);
        $this->assertGreaterThan(0, (float) $issue->fine_amount);
        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_issue_page_uses_database_backed_copy_and_borrower_selectors(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 2,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.library.issues'))
            ->assertOk()
            ->assertSee('Select available copy')
            ->assertSee($book->title)
            ->assertSee($copy->accession_number)
            ->assertSee($student->user->name)
            ->assertDontSee('Book Copy ID')
            ->assertDontSee('Borrower ID');

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $copy->id,
                'borrower_key' => 'student:'.$student->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Book issued successfully.');

        $this->assertDatabaseHas('book_issues', [
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'status' => 'issued',
        ]);
    }

    public function test_membership_limit_blocks_extra_active_issue(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$firstBook, $firstCopy] = $this->bookWithCopy(['title' => 'First Book']);
        [$secondBook, $secondCopy] = $this->bookWithCopy(['title' => 'Second Book']);

        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 1,
            'max_days_allowed' => 14,
            'fine_per_day' => 1,
            'is_active' => true,
        ]);

        BookIssue::create([
            'book_copy_id' => $firstCopy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'issued',
        ]);
        $firstCopy->update(['is_available' => false]);
        $firstBook->update(['available_copies' => 0]);

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $secondCopy->id,
                'borrower_type' => 'student',
                'borrower_id' => $student->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('borrower_id');

        $this->assertTrue($secondCopy->fresh()->is_available);
        $this->assertSame(1, $secondBook->fresh()->available_copies);
    }

    public function test_student_can_reserve_unavailable_book_and_cancel_own_reservation(): void
    {
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();
        $book->update(['available_copies' => 0]);
        $copy->update(['is_available' => false]);

        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($student->user)
            ->get(route('student.library.index'))
            ->assertOk()
            ->assertSee('Catalog And Reservations')
            ->assertSee('Reserve');

        $this->actingAs($student->user)
            ->post(route('student.library.reservations.store'), ['book_id' => $book->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Book reservation added.');

        $reservation = LibraryReservation::firstOrFail();
        $this->assertSame($book->id, $reservation->book_id);
        $this->assertSame($student->id, $reservation->student_id);
        $this->assertSame('pending', $reservation->status);

        $this->actingAs($student->user)
            ->post(route('student.library.reservations.cancel', $reservation))
            ->assertRedirect()
            ->assertSessionHas('success', 'Reservation cancelled.');

        $this->assertSame('cancelled', $reservation->fresh()->status);
    }

    public function test_reserved_book_cannot_be_issued_to_another_borrower_before_queue_is_served(): void
    {
        $admin = $this->userWithRole('admin');
        $reservedStudent = $this->student();
        $otherStudent = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        foreach ([$reservedStudent, $otherStudent] as $student) {
            LibraryMembership::create([
                'user_id' => $student->user_id,
                'member_type' => 'student',
                'max_books_allowed' => 2,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
                'is_active' => true,
            ]);
        }

        LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $reservedStudent->id,
            'reserved_at' => now()->subHour(),
            'expires_at' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $copy->id,
                'borrower_type' => 'student',
                'borrower_id' => $otherStudent->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('book_copy_id');

        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertDatabaseMissing('book_issues', [
            'book_copy_id' => $copy->id,
            'student_id' => $otherStudent->id,
        ]);
    }

    public function test_admin_can_fulfill_pending_reservation_into_issue(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 10,
            'fine_per_day' => 1,
            'is_active' => true,
        ]);

        $reservation = LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'reserved_at' => now()->subHour(),
            'expires_at' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.library.reservations'))
            ->assertOk()
            ->assertSee($book->title)
            ->assertSee($student->user->name);

        $this->actingAs($admin)
            ->post(route('admin.library.reservations.fulfill', $reservation))
            ->assertRedirect()
            ->assertSessionHas('success', 'Reservation fulfilled and book issued.');

        $this->assertSame('fulfilled', $reservation->fresh()->status);
        $this->assertFalse($copy->fresh()->is_available);
        $this->assertSame(0, $book->fresh()->available_copies);
        $this->assertDatabaseHas('book_issues', [
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'status' => 'issued',
        ]);
    }

    public function test_issue_due_date_cannot_exceed_membership_allowed_days(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 3,
            'fine_per_day' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $copy->id,
                'borrower_type' => 'student',
                'borrower_id' => $student->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('due_date');

        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_library_noc_eligibility_is_scoped_to_the_requested_user(): void
    {
        $student = $this->student();
        $otherTeacher = Teacher::factory()->create();
        [$book, $copy] = $this->bookWithCopy();

        BookIssue::create([
            'book_copy_id' => $copy->id,
            'teacher_id' => $otherTeacher->id,
            'issued_by' => $this->userWithRole('admin')->id,
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'issued',
        ]);

        $eligibility = app(LibraryFineService::class)->checkNocEligibility($student->user_id);

        $this->assertTrue($eligibility['eligible']);
        $this->assertNull($eligibility['reason']);
    }

    public function test_library_noc_eligibility_blocks_unpaid_returned_fines_for_requested_user(): void
    {
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $this->userWithRole('admin')->id,
            'issued_at' => now()->subDays(10),
            'due_date' => now()->subDays(5)->toDateString(),
            'returned_at' => now()->subDay(),
            'fine_amount' => 25,
            'fine_paid' => false,
            'status' => 'returned',
        ]);

        $eligibility = app(LibraryFineService::class)->checkNocEligibility($student->user_id);

        $this->assertFalse($eligibility['eligible']);
        $this->assertSame('Has unpaid library fines', $eligibility['reason']);
    }
}
