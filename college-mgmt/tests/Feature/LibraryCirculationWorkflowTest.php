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

    private function teacher(string $status = 'active'): Teacher
    {
        $user = $this->userWithRole('teacher');

        return Teacher::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
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

    public function test_admin_library_pages_use_readable_operational_fallbacks(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $copy = new BookCopy(['accession_number' => null]);
        $copy->id = 111;
        $copy->setRelation('book', null);

        $issue = new BookIssue([
            'issued_at' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'issued',
            'fine_amount' => 0,
        ]);
        $issue->id = 222;
        $issue->setRelation('bookCopy', $copy);
        $issue->setRelation('student', null);
        $issue->setRelation('teacher', null);

        $membership = new LibraryMembership([
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 1.5,
            'is_active' => true,
            'expiry_date' => null,
        ]);
        $membership->id = 333;
        $membership->setRelation('user', null);

        $issues = new \Illuminate\Pagination\LengthAwarePaginator(collect([$issue]), 1, 15);
        $memberships = new \Illuminate\Pagination\LengthAwarePaginator(collect([$membership]), 1, 15);

        $dashboardHtml = view('admin.library.index', [
            'totalBooks' => 0,
            'totalCopies' => 0,
            'issuedToday' => 0,
            'overdueCount' => 0,
            'dueToday' => 0,
            'finesPending' => 0,
            'latestIssues' => collect([$issue]),
        ])->render();

        $issuesHtml = view('admin.library.issues', [
            'issues' => $issues,
            'availableCopies' => collect(),
            'students' => collect(),
            'teachers' => collect(),
        ])->render();

        $membershipsHtml = view('admin.library.memberships', [
            'memberships' => $memberships,
            'users' => collect(),
        ])->render();

        foreach ([$dashboardHtml, $issuesHtml, $membershipsHtml] as $html) {
            $this->assertStringNotContainsString('N/A', $html);
            $this->assertStringNotContainsString('â', $html);
            $this->assertStringNotContainsString('&mdash;', $html);
            $this->assertStringNotContainsString('&ndash;', $html);
        }

        $this->assertStringContainsString('Book title missing', $dashboardHtml);
        $this->assertStringContainsString('Borrower not linked', $issuesHtml);
        $this->assertStringContainsString('No fine', $issuesHtml);
        $this->assertStringContainsString('Member name missing', $membershipsHtml);
        $this->assertStringContainsString('Email not linked', $membershipsHtml);
        $this->assertStringContainsString('Rs. 1.50', $membershipsHtml);
        $this->assertStringContainsString('Select user', $membershipsHtml);
    }

    public function test_admin_library_books_issues_and_reservations_export_current_filtered_view(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$matchingBook, $copy] = $this->bookWithCopy(['title' => 'Filtered Library Book', 'author' => 'Export Author']);
        [$otherBook] = $this->bookWithCopy(['title' => 'Unmatched Library Book']);
        $issue = BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'issued',
        ]);
        $copy->update(['is_available' => false]);
        $matchingBook->update(['available_copies' => 0]);
        $reservation = LibraryReservation::create([
            'book_id' => $matchingBook->id,
            'student_id' => $student->id,
            'reserved_at' => now(),
            'expires_at' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.library.books', ['search' => 'Filtered']))
            ->assertOk()
            ->assertSee(route('admin.library.books.export', ['search' => 'Filtered']))
            ->assertSee('Showing 1 book record(s)')
            ->assertSee($matchingBook->title)
            ->assertDontSee($otherBook->title);

        $bookCsv = $this->actingAs($admin)
            ->get(route('admin.library.books.export', ['search' => 'Filtered']))
            ->streamedContent();
        $this->assertStringContainsString('Filtered Library Book', $bookCsv);
        $this->assertStringNotContainsString('Unmatched Library Book', $bookCsv);

        $this->actingAs($admin)
            ->get(route('admin.library.issues', ['status' => 'issued']))
            ->assertOk()
            ->assertSee(route('admin.library.issues.export', ['status' => 'issued']))
            ->assertSee('Showing 1 issue record(s)');

        $issueCsv = $this->actingAs($admin)
            ->get(route('admin.library.issues.export', ['status' => 'issued']))
            ->streamedContent();
        $this->assertStringContainsString($copy->accession_number, $issueCsv);
        $this->assertStringContainsString('issued', $issueCsv);

        $this->actingAs($admin)
            ->get(route('admin.library.reservations', ['status' => 'pending', 'search' => 'Filtered']))
            ->assertOk()
            ->assertSee(route('admin.library.reservations.export', ['status' => 'pending', 'search' => 'Filtered']))
            ->assertSee('Showing 1 reservation record(s)')
            ->assertSee($reservation->book->title);

        $reservationCsv = $this->actingAs($admin)
            ->get(route('admin.library.reservations.export', ['status' => 'pending', 'search' => 'Filtered']))
            ->streamedContent();
        $this->assertStringContainsString('Filtered Library Book', $reservationCsv);
        $this->assertStringContainsString('pending', $reservationCsv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'export',
            'description' => 'Library reservations exported: 1 rows; filters={"status":"pending","search":"Filtered"}',
        ]);
    }

    public function test_admin_library_memberships_and_fines_export_current_view(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy(['title' => 'Fine Export Book']);
        LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 2,
            'is_active' => true,
        ]);
        BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now()->subDays(10),
            'due_date' => now()->subDays(5)->toDateString(),
            'returned_at' => now()->subDay(),
            'fine_amount' => 30,
            'fine_paid' => false,
            'status' => 'returned',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.library.memberships'))
            ->assertOk()
            ->assertSee(route('admin.library.memberships.export'))
            ->assertSee('Showing 1 membership record(s)');

        $membershipCsv = $this->actingAs($admin)
            ->get(route('admin.library.memberships.export'))
            ->streamedContent();
        $this->assertStringContainsString($student->user->email, $membershipCsv);
        $this->assertStringContainsString('student', $membershipCsv);

        $this->actingAs($admin)
            ->get(route('admin.library.fines'))
            ->assertOk()
            ->assertSee(route('admin.library.fines.export'))
            ->assertSee('Showing 1 unpaid fine record(s).');

        $fineCsv = $this->actingAs($admin)
            ->get(route('admin.library.fines.export'))
            ->streamedContent();
        $this->assertStringContainsString('Fine Export Book', $fineCsv);
        $this->assertStringContainsString('30', $fineCsv);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'export',
            'description' => 'Library unpaid fines exported: 1 rows; filters=none',
        ]);
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

    public function test_library_issue_selector_and_book_level_issue_ignore_damaged_or_lost_copies(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $book = Book::create([
            'title' => 'Clean Copy Selection',
            'author' => 'Library Team',
            'isbn' => 'ISBN-' . uniqid(),
            'category' => 'Operations',
            'total_copies' => 2,
            'available_copies' => 2,
            'is_active' => true,
        ]);
        $damagedCopy = BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'ACC-DAMAGED-FIRST',
            'condition_status' => 'damaged',
            'is_available' => true,
        ]);
        $cleanCopy = BookCopy::create([
            'book_id' => $book->id,
            'accession_number' => 'ACC-CLEAN-SECOND',
            'condition_status' => 'good',
            'is_available' => true,
        ]);

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
            ->assertSee('ACC-CLEAN-SECOND')
            ->assertDontSee('ACC-DAMAGED-FIRST');

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_id' => $book->id,
                'borrower_type' => 'student',
                'borrower_id' => $student->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Book issued successfully.');

        $this->assertDatabaseHas('book_issues', [
            'book_copy_id' => $cleanCopy->id,
            'student_id' => $student->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseMissing('book_issues', [
            'book_copy_id' => $damagedCopy->id,
            'student_id' => $student->id,
        ]);
        $this->assertTrue($damagedCopy->fresh()->is_available);
        $this->assertFalse($cleanCopy->fresh()->is_available);
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

    public function test_library_issue_requires_active_student_or_teacher_borrower(): void
    {
        $admin = $this->userWithRole('admin');
        $inactiveStudent = $this->student();
        $inactiveStudent->update(['status' => 'inactive']);
        $inactiveTeacher = $this->teacher('inactive');
        [$studentBook, $studentCopy] = $this->bookWithCopy(['title' => 'Student Direct Route Book']);
        [$teacherBook, $teacherCopy] = $this->bookWithCopy(['title' => 'Teacher Direct Route Book']);

        foreach ([$inactiveStudent->user_id, $inactiveTeacher->user_id] as $userId) {
            LibraryMembership::create([
                'user_id' => $userId,
                'member_type' => 'student',
                'max_books_allowed' => 2,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $studentCopy->id,
                'borrower_type' => 'student',
                'borrower_id' => $inactiveStudent->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('borrower_id');

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'book_copy_id' => $teacherCopy->id,
                'borrower_type' => 'teacher',
                'borrower_id' => $inactiveTeacher->id,
                'due_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertSessionHasErrors('borrower_id');

        $this->assertTrue($studentCopy->fresh()->is_available);
        $this->assertTrue($teacherCopy->fresh()->is_available);
        $this->assertSame(1, $studentBook->fresh()->available_copies);
        $this->assertSame(1, $teacherBook->fresh()->available_copies);
        $this->assertSame(0, BookIssue::count());
    }

    public function test_membership_limit_cannot_be_lowered_below_active_issue_count(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$firstBook, $firstCopy] = $this->bookWithCopy(['title' => 'Membership Limit One']);
        [$secondBook, $secondCopy] = $this->bookWithCopy(['title' => 'Membership Limit Two']);

        $membership = LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 3,
            'max_days_allowed' => 14,
            'fine_per_day' => 1,
            'is_active' => true,
        ]);

        foreach ([$firstCopy, $secondCopy] as $copy) {
            BookIssue::create([
                'book_copy_id' => $copy->id,
                'student_id' => $student->id,
                'issued_by' => $admin->id,
                'issued_at' => now(),
                'due_date' => now()->addDays(7)->toDateString(),
                'status' => 'issued',
            ]);
            $copy->update(['is_available' => false]);
        }
        $firstBook->update(['available_copies' => 0]);
        $secondBook->update(['available_copies' => 0]);

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $student->user_id,
                'member_type' => 'student',
                'max_books_allowed' => 1,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
            ])
            ->assertSessionHasErrors('max_books_allowed');

        $membership->refresh();
        $this->assertSame(3, $membership->max_books_allowed);
        $this->assertSame(2, BookIssue::where('student_id', $student->id)->whereIn('status', ['issued', 'overdue'])->count());
    }

    public function test_membership_limit_cannot_be_bypassed_by_changing_member_type(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$firstBook, $firstCopy] = $this->bookWithCopy(['title' => 'Membership Type Bypass One']);
        [$secondBook, $secondCopy] = $this->bookWithCopy(['title' => 'Membership Type Bypass Two']);

        $membership = LibraryMembership::create([
            'user_id' => $student->user_id,
            'member_type' => 'student',
            'max_books_allowed' => 2,
            'max_days_allowed' => 14,
            'fine_per_day' => 1,
            'is_active' => true,
        ]);

        foreach ([[$firstBook, $firstCopy], [$secondBook, $secondCopy]] as [$book, $copy]) {
            BookIssue::create([
                'book_copy_id' => $copy->id,
                'student_id' => $student->id,
                'issued_by' => $admin->id,
                'issued_at' => now(),
                'due_date' => now()->addDays(7)->toDateString(),
                'status' => 'issued',
            ]);
            $copy->update(['is_available' => false]);
            $book->update(['available_copies' => 0]);
        }

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $student->user_id,
                'member_type' => 'staff',
                'max_books_allowed' => 1,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
            ])
            ->assertSessionHasErrors('member_type');

        $membership->refresh();
        $this->assertSame('student', $membership->member_type);
        $this->assertSame(2, $membership->max_books_allowed);
    }

    public function test_library_membership_type_must_match_linked_user_profile(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        $teacher = $this->teacher();
        $staff = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $student->user_id,
                'member_type' => 'staff',
                'max_books_allowed' => 2,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
            ])
            ->assertSessionHasErrors('member_type');

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $teacher->user_id,
                'member_type' => 'student',
                'max_books_allowed' => 2,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
            ])
            ->assertSessionHasErrors('member_type');

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $staff->id,
                'member_type' => 'teacher',
                'max_books_allowed' => 2,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
            ])
            ->assertSessionHasErrors('member_type');

        $this->assertDatabaseMissing('library_memberships', [
            'user_id' => $student->user_id,
            'member_type' => 'staff',
        ]);
        $this->assertDatabaseMissing('library_memberships', [
            'user_id' => $teacher->user_id,
            'member_type' => 'student',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $student->user_id,
                'member_type' => 'student',
                'max_books_allowed' => 2,
                'max_days_allowed' => 14,
                'fine_per_day' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Membership saved.');

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $teacher->user_id,
                'member_type' => 'teacher',
                'max_books_allowed' => 3,
                'max_days_allowed' => 21,
                'fine_per_day' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Membership saved.');

        $this->actingAs($admin)
            ->post(route('admin.library.memberships.store'), [
                'user_id' => $staff->id,
                'member_type' => 'staff',
                'max_books_allowed' => 1,
                'max_days_allowed' => 7,
                'fine_per_day' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Membership saved.');

        $this->assertDatabaseHas('library_memberships', [
            'user_id' => $student->user_id,
            'member_type' => 'student',
        ]);
        $this->assertDatabaseHas('library_memberships', [
            'user_id' => $teacher->user_id,
            'member_type' => 'teacher',
        ]);
        $this->assertDatabaseHas('library_memberships', [
            'user_id' => $staff->id,
            'member_type' => 'staff',
        ]);
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

    public function test_student_library_empty_states_explain_borrowing_reservations_and_history(): void
    {
        $student = $this->student();

        $this->actingAs($student->user)
            ->get(route('student.library.index'))
            ->assertOk()
            ->assertSee('No books currently borrowed')
            ->assertSee('Issued books will appear here with due dates')
            ->assertSee('No reservations yet')
            ->assertSee('Reserve a book only when all issuable copies are unavailable')
            ->assertSee('No catalog books are available yet')
            ->assertSee('library team must add books and issuable copies')
            ->assertSee('No borrowing history yet')
            ->assertSee('Past issues, returns, and paid fines will appear here')
            ->assertDontSee('No reservations yet.')
            ->assertDontSee('â€”')
            ->assertDontSee('â‚¹');
    }

    public function test_inactive_student_cannot_create_library_reservation_through_direct_route(): void
    {
        $student = $this->student();
        $student->update(['status' => 'inactive']);
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
            ->post(route('student.library.reservations.store'), ['book_id' => $book->id])
            ->assertSessionHasErrors('book_id');

        $this->assertSame(0, LibraryReservation::count());
    }

    public function test_student_cannot_reserve_title_already_issued_to_them(): void
    {
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy(['title' => 'Already Issued Reservation Guard']);
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
        BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $this->userWithRole('admin')->id,
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'issued',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.library.reservations.store'), ['book_id' => $book->id])
            ->assertRedirect()
            ->assertSessionHasErrors('book_id');

        $this->assertSame(0, LibraryReservation::count());
    }

    public function test_student_cannot_cancel_expired_pending_reservation_as_cancelled_history(): void
    {
        $student = $this->student();
        [$book] = $this->bookWithCopy();

        $reservation = LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'reserved_at' => now()->subDays(10),
            'expires_at' => now()->subDay()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($student->user)
            ->post(route('student.library.reservations.cancel', $reservation))
            ->assertRedirect()
            ->assertSessionHasErrors('reservation');

        $this->assertSame('expired', $reservation->fresh()->status);
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

    public function test_admin_cannot_cancel_expired_pending_reservation_as_cancelled_history(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book] = $this->bookWithCopy();

        $reservation = LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'reserved_at' => now()->subDays(10),
            'expires_at' => now()->subDay()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.reservations.cancel', $reservation))
            ->assertRedirect()
            ->assertSessionHasErrors('reservation');

        $this->assertSame('expired', $reservation->fresh()->status);
    }

    public function test_admin_cannot_fulfill_reservation_for_inactive_borrower(): void
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

        $student->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->post(route('admin.library.reservations.fulfill', $reservation))
            ->assertRedirect()
            ->assertSessionHasErrors('reservation');

        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertDatabaseMissing('book_issues', [
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_cannot_fulfill_reservation_for_inactive_book_title(): void
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

        $book->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('admin.library.reservations.fulfill', $reservation))
            ->assertRedirect()
            ->assertSessionHasErrors('reservation');

        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame(1, $book->fresh()->available_copies);
        $this->assertDatabaseMissing('book_issues', [
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_admin_cannot_fulfill_later_reservation_before_earlier_queue_entry(): void
    {
        $admin = $this->userWithRole('admin');
        $firstStudent = $this->student();
        $secondStudent = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        foreach ([$firstStudent, $secondStudent] as $student) {
            LibraryMembership::create([
                'user_id' => $student->user_id,
                'member_type' => 'student',
                'max_books_allowed' => 2,
                'max_days_allowed' => 10,
                'fine_per_day' => 1,
                'is_active' => true,
            ]);
        }

        $earlierReservation = LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $firstStudent->id,
            'reserved_at' => now()->subHours(2),
            'expires_at' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $laterReservation = LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $secondStudent->id,
            'reserved_at' => now()->subHour(),
            'expires_at' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.reservations.fulfill', $laterReservation))
            ->assertRedirect()
            ->assertSessionHasErrors('reservation');

        $this->assertSame('pending', $earlierReservation->fresh()->status);
        $this->assertSame('pending', $laterReservation->fresh()->status);
        $this->assertTrue($copy->fresh()->is_available);
        $this->assertSame(0, BookIssue::count());
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

    public function test_active_overdue_fine_cannot_be_marked_paid_before_final_return(): void
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

        $issue = BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now()->subDays(12),
            'due_date' => now()->subDays(4)->toDateString(),
            'fine_amount' => 8,
            'fine_paid' => false,
            'status' => 'overdue',
        ]);
        $copy->update(['is_available' => false]);
        $book->update(['available_copies' => 0]);

        $this->actingAs($admin)
            ->post(route('admin.library.fines.pay', $issue))
            ->assertRedirect()
            ->assertSessionHasErrors('fine');

        $this->assertFalse($issue->fresh()->fine_paid);

        $this->actingAs($admin)
            ->post(route('admin.library.issues.return', $issue))
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame('returned', $issue->status);
        $this->assertGreaterThan(0, (float) $issue->fine_amount);
        $this->assertFalse($issue->fine_paid);
    }

    public function test_lost_book_issue_cannot_be_returned_through_direct_route(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        $issue = BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now()->subDays(20),
            'due_date' => now()->subDays(10)->toDateString(),
            'fine_amount' => 100,
            'fine_paid' => false,
            'status' => 'lost',
        ]);
        $copy->update([
            'is_available' => false,
            'condition_status' => 'lost',
        ]);
        $book->update(['available_copies' => 0]);

        $this->actingAs($admin)
            ->post(route('admin.library.issues.return', $issue))
            ->assertRedirect()
            ->assertSessionHas('error', 'Only active issued or overdue books can be returned.');

        $issue->refresh();
        $this->assertSame('lost', $issue->status);
        $this->assertNull($issue->returned_at);
        $this->assertFalse($copy->fresh()->is_available);
        $this->assertSame('lost', $copy->fresh()->condition_status);
        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_returned_positive_fine_can_be_paid_once_only(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->student();
        [$book, $copy] = $this->bookWithCopy();

        $issue = BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $student->id,
            'issued_by' => $admin->id,
            'issued_at' => now()->subDays(10),
            'due_date' => now()->subDays(5)->toDateString(),
            'returned_at' => now()->subDay(),
            'fine_amount' => 25,
            'fine_paid' => false,
            'status' => 'returned',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.library.fines.pay', $issue))
            ->assertRedirect()
            ->assertSessionHas('success', 'Fine of Rs. 25.00 marked as paid.');

        $this->assertTrue($issue->fresh()->fine_paid);
        $this->assertNotNull($issue->fresh()->fine_paid_at);
        $this->assertSame($admin->id, $issue->fresh()->fine_collected_by);

        $this->actingAs($admin)
            ->post(route('admin.library.fines.pay', $issue))
            ->assertRedirect()
            ->assertSessionHasErrors('fine');
    }
}
