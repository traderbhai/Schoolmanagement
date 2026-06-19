<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\LibraryMembership;
use App\Models\LibraryReservation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLibraryAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_director_can_open_library_operations(): void
    {
        foreach (['admin', 'director'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.library.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.library.books'))->assertOk();
        }
    }

    public function test_broad_admin_group_roles_cannot_read_library_operations(): void
    {
        [$book] = $this->bookWithCopy();

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.library.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.library.books'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.library.books.show', $book))->assertForbidden();
            $this->actingAs($user)->get(route('admin.library.issues'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.library.reservations'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.library.memberships'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.library.fines'))->assertForbidden();
        }
    }

    public function test_broad_admin_group_roles_cannot_mutate_library_operations(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();
        $membership = LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 2,
            'is_active' => true,
        ]);
        $issue = BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now()->subDays(10),
            'due_date' => now()->subDays(3)->toDateString(),
            'returned_at' => now()->subDay(),
            'fine_amount' => 25,
            'fine_paid' => false,
            'status' => 'returned',
        ]);
        $reservation = LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'reserved_at' => now()->subHour(),
            'expires_at' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        foreach (['dean_academics', 'program_chair', 'hod', 'exam_cell', 'accounts_officer', 'cmc'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)
                ->post(route('admin.library.books.store'), [
                    'title' => "Blocked Book {$role}",
                    'author' => 'Blocked Author',
                    'total_copies' => 1,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.library.books.update', $book), [
                    'title' => "Changed Title {$role}",
                    'author' => 'Changed Author',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.library.issue'), [
                    'book_copy_id' => $copy->id,
                    'borrower_type' => 'student',
                    'borrower_id' => $student->id,
                    'due_date' => now()->addDays(7)->toDateString(),
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.library.issues.return', $issue))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.library.reservations.fulfill', $reservation))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.library.reservations.cancel', $reservation))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.library.memberships.store'), [
                    'user_id' => $student->user_id,
                    'member_type' => 'student',
                    'max_books_allowed' => 5,
                    'max_days_allowed' => 30,
                    'fine_per_day' => 1,
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.library.fines.pay', $issue))
                ->assertForbidden();
        }

        $this->assertSame('Database Systems', $book->fresh()->title);
        $this->assertSame(1, Book::count());
        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame('returned', $issue->fresh()->status);
        $this->assertFalse($issue->fresh()->fine_paid);
        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertSame(2, $membership->fresh()->max_books_allowed);
    }

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

    private function bookWithCopy(): array
    {
        $book = Book::create([
            'title' => 'Database Systems',
            'author' => 'Ramakrishnan',
            'isbn' => 'ISBN-' . uniqid(),
            'category' => 'Computer Science',
            'total_copies' => 1,
            'available_copies' => 1,
            'is_active' => true,
        ]);

        $copy = BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'ACC-' . uniqid(),
            'is_available' => true,
        ]);

        return [$book, $copy];
    }
}
