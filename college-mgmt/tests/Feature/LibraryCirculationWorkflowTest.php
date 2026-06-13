<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\LibraryMembership;
use App\Models\Student;
use App\Models\User;
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
}
