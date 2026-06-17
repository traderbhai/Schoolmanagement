<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Book, BookCopy, BookIssue, LibraryMembership, LibraryReservation, Student, Teacher, User};
use App\Services\LibraryFineService;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function __construct(private LibraryFineService $fines) {}

    public function index()
    {
        $totalBooks = Book::where('is_active', true)->count();
        $totalCopies = BookCopy::count();
        $issuedToday = BookIssue::whereDate('issued_at', today())->count();
        $overdueCount = BookIssue::where('status','overdue')->count();
        $dueToday = BookIssue::whereIn('status', ['issued', 'overdue'])->whereDate('due_date', today())->count();
        $finesPending = BookIssue::where('fine_paid', false)->where('fine_amount', '>', 0)->count();
        $latestIssues = BookIssue::with(['bookCopy.book','student.user','teacher.user'])
            ->latest()->limit(10)->get();

        return view('admin.library.index', compact(
            'totalBooks',
            'totalCopies',
            'issuedToday',
            'overdueCount',
            'dueToday',
            'finesPending',
            'latestIssues'
        ));
    }

    public function books(Request $r)
    {
        $query = Book::withCount([
            'copies',
            'availableCopies as active_copies_count',
            'issues' => fn($q) => $q->whereIn('status',['issued','overdue']),
        ]);
        if ($r->filled('search')) {
            $s = $r->search;
            $query->where(fn($q) => $q->where('title','like',"%$s%")->orWhere('author','like',"%$s%")->orWhere('isbn','like',"%$s%"));
        }
        $books = $query->orderBy('title')->paginate(20)->withQueryString();
        return view('admin.library.books', compact('books'));
    }

    public function bookStore(Request $r)
    {
        $data = $r->validate([
            'title'                => 'required|string|max:300',
            'author'               => 'required|string|max:200',
            'isbn'                 => 'nullable|string|max:20|unique:books,isbn',
            'publisher'            => 'nullable|string|max:200',
            'edition'              => 'nullable|string|max:50',
            'year_of_publication'  => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'category'             => 'nullable|string|max:100',
            'language'             => 'nullable|string|max:50',
            'total_copies'         => 'nullable|integer|min:1|max:999',
            'location'             => 'nullable|string|max:100',
            'description'          => 'nullable|string',
        ]);
        $copies = (int)($data['total_copies'] ?? 1);
        $data['total_copies']     = $copies;
        $data['available_copies'] = $copies;
        $book = Book::create($data);
        $year = date('Y');
        $lastAcc = BookCopy::orderByDesc('id')->value('accession_number');
        $seq = $lastAcc ? ((int)substr($lastAcc, -5) + 1) : 1;
        for ($i = 0; $i < $copies; $i++) {
            BookCopy::create([
                'book_id'          => $book->id,
                'accession_number' => 'ACC-' . $year . '-' . str_pad($seq++, 5, '0', STR_PAD_LEFT),
            ]);
        }
        return back()->with('success', "Book \"{$book->title}\" added with {$copies} copy/copies.");
    }

    public function bookShow(Book $book)
    {
        $book->load(['copies.currentIssue.student.user', 'copies.currentIssue.teacher.user']);
        $copies = $book->copies;
        $issueHistory = BookIssue::whereHas('bookCopy', fn($q) => $q->where('book_id', $book->id))
            ->with(['bookCopy','student.user','teacher.user'])->latest()->limit(50)->get();
        return view('admin.library.book-show', compact('book', 'copies', 'issueHistory'));
    }

    public function bookUpdate(Request $r, Book $book)
    {
        $data = $r->validate([
            'title'       => 'required|string|max:300',
            'author'      => 'required|string|max:200',
            'isbn'        => 'nullable|string|max:20|unique:books,isbn,' . $book->id,
            'publisher'   => 'nullable|string|max:200',
            'edition'     => 'nullable|string|max:50',
            'category'    => 'nullable|string|max:100',
            'location'    => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);
        $book->update($data);
        return back()->with('success', 'Book updated.');
    }

    public function issueBook(Request $r)
    {
        $this->expireStaleReservations();

        $data = $r->validate([
            'book_copy_id'  => 'nullable|exists:book_copies,id',
            'book_id'       => 'nullable|exists:books,id',
            'borrower_key'  => 'nullable|string',
            'borrower_type' => 'required_without:borrower_key|in:student,teacher',
            'borrower_id'   => 'required_without:borrower_key|integer',
            'due_date'      => 'required|date|after:today',
        ]);

        if (! empty($data['borrower_key'])) {
            [$data['borrower_type'], $data['borrower_id']] = explode(':', $data['borrower_key'], 2) + [null, null];
        }

        if (! in_array($data['borrower_type'] ?? null, ['student', 'teacher'], true) || ! ctype_digit((string) ($data['borrower_id'] ?? ''))) {
            return back()->withErrors(['borrower_id' => 'Select a valid borrower.']);
        }

        if (empty($data['book_copy_id']) && empty($data['book_id'])) {
            return back()->withErrors(['book_copy_id' => 'Select a book copy or book to issue.']);
        }

        $borrowerColumn = $data['borrower_type'] === 'student' ? 'student_id' : 'teacher_id';
        $borrowerClass = $data['borrower_type'] === 'student' ? Student::class : Teacher::class;
        $borrower = $borrowerClass::find($data['borrower_id']);
        if (! $borrower) {
            return back()->withErrors(['borrower_id' => 'Selected borrower was not found.']);
        }

        if (($borrower->status ?? null) !== 'active') {
            return back()->withErrors(['borrower_id' => 'Only active students or teachers can be issued library books.']);
        }

        $membership = LibraryMembership::where('user_id', $borrower->user_id)->where('is_active', true)->first();
        if ($membership && $membership->expiry_date && $membership->expiry_date->isPast()) {
            return back()->withErrors(['borrower_id' => 'Library membership has expired.']);
        }

        if ($membership && $data['due_date'] > now()->addDays((int) $membership->max_days_allowed)->toDateString()) {
            return back()->withErrors(['due_date' => "Due date cannot exceed {$membership->max_days_allowed} day(s) for this membership."]);
        }

        $activeIssues = BookIssue::where($borrowerColumn, $borrower->id)->whereIn('status', ['issued', 'overdue'])->count();
        $maxBooks = $membership?->max_books_allowed ?? 2;
        if ($activeIssues >= $maxBooks) {
            return back()->withErrors(['borrower_id' => "Borrower already has the maximum {$maxBooks} active issue(s)."]);
        }

        $copy = ! empty($data['book_copy_id'])
            ? BookCopy::where('id', $data['book_copy_id'])->where('is_available', true)->first()
            : BookCopy::where('book_id', $data['book_id'])->where('is_available', true)->first();

        if (! $copy) {
            return back()->with('error', 'No available copy for this book.');
        }

        $copy->load('book');
        if (! $copy->book?->is_active || in_array($copy->condition_status, ['damaged', 'lost'], true)) {
            return back()->withErrors(['book_copy_id' => 'Selected copy is not issuable.']);
        }

        $reservation = $this->matchingPendingReservation($copy->book_id, $data['borrower_type'], (int) $borrower->id);
        $earliestReservation = $this->earliestPendingReservation($copy->book_id);
        if ($earliestReservation && ! $reservation) {
            return back()->withErrors(['book_copy_id' => 'This title is reserved for another borrower. Fulfil or cancel the reservation first.']);
        }

        BookIssue::create([
            'book_copy_id' => $copy->id,
            $borrowerColumn => $borrower->id,
            'issued_by'    => auth()->id(),
            'issued_at'    => now(),
            'due_date'     => $data['due_date'],
            'status'       => 'issued',
        ]);
        $copy->update(['is_available' => false]);
        Book::where('id', $copy->book_id)->decrement('available_copies');
        $reservation?->update(['status' => 'fulfilled']);
        return back()->with('success', 'Book issued successfully.');
    }

    public function returnBook(BookIssue $issue)
    {
        if ($issue->returned_at) return back()->with('error', 'Book already returned.');

        if (! in_array($issue->status, ['issued', 'overdue'], true)) {
            return back()->with('error', 'Only active issued or overdue books can be returned.');
        }

        $fine = $this->fines->calculateFine($issue);
        $issue->update([
            'returned_at'         => now(),
            'return_accepted_by'  => auth()->id(),
            'fine_amount'         => $fine,
            'fine_paid'           => $fine <= 0,
            'status'              => 'returned',
        ]);
        $issue->bookCopy->update(['is_available' => true]);
        Book::where('id', $issue->bookCopy->book_id)->increment('available_copies');
        return back()->with('success', $fine > 0 ? 'Returned. Fine: Rs. ' . number_format($fine, 2) : 'Book returned successfully.');
    }

    public function issues(Request $r)
    {
        $this->expireStaleReservations();

        $query = BookIssue::with(['bookCopy.book','student.user','teacher.user']);
        if ($r->filled('status')) $query->where('status', $r->status);
        $issues = $query->latest()->paginate(20)->withQueryString();
        $availableCopies = BookCopy::with('book')
            ->where('is_available', true)
            ->whereHas('book', fn ($book) => $book->where('is_active', true))
            ->orderBy('accession_number')
            ->limit(200)
            ->get();
        $students = Student::with('user')
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->limit(200)
            ->get();
        $teachers = Teacher::with('user')
            ->where('status', 'active')
            ->orderBy('employee_id')
            ->limit(200)
            ->get();

        return view('admin.library.issues', compact('issues', 'availableCopies', 'students', 'teachers'));
    }

    public function reservations(Request $r)
    {
        $this->expireStaleReservations();

        $query = LibraryReservation::with(['book', 'student.user', 'teacher.user']);
        if ($r->filled('status') && $r->status !== 'all') {
            $query->where('status', $r->status);
        }
        if ($r->filled('search')) {
            $search = $r->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('book', fn ($book) => $book->where('title', 'like', "%{$search}%")->orWhere('isbn', 'like', "%{$search}%"))
                    ->orWhereHas('student.user', fn ($user) => $user->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('teacher.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        $reservations = $query->latest('reserved_at')->paginate(20)->withQueryString();
        $availableCopiesByBook = BookCopy::where('is_available', true)
            ->whereNotIn('condition_status', ['damaged', 'lost'])
            ->selectRaw('book_id, count(*) as available_count')
            ->groupBy('book_id')
            ->pluck('available_count', 'book_id');

        return view('admin.library.reservations', compact('reservations', 'availableCopiesByBook'));
    }

    public function fulfillReservation(Request $r, LibraryReservation $reservation)
    {
        $this->expireStaleReservations();

        if ($reservation->status !== 'pending') {
            return back()->withErrors(['reservation' => 'Only pending reservations can be fulfilled.']);
        }

        $reservation->load(['book', 'student', 'teacher']);
        $borrower = $reservation->student ?: $reservation->teacher;
        $borrowerType = $reservation->student ? 'student' : 'teacher';
        if (! $borrower) {
            return back()->withErrors(['reservation' => 'Reservation borrower no longer exists.']);
        }

        $membership = LibraryMembership::where('user_id', $borrower->user_id)->where('is_active', true)->first();
        if ($membership && $membership->expiry_date && $membership->expiry_date->isPast()) {
            return back()->withErrors(['reservation' => 'Borrower membership has expired.']);
        }

        $borrowerColumn = $borrowerType === 'student' ? 'student_id' : 'teacher_id';
        $maxBooks = $membership?->max_books_allowed ?? 2;
        $activeIssues = BookIssue::where($borrowerColumn, $borrower->id)->whereIn('status', ['issued', 'overdue'])->count();
        if ($activeIssues >= $maxBooks) {
            return back()->withErrors(['reservation' => "Borrower already has the maximum {$maxBooks} active issue(s)."]);
        }

        $copy = BookCopy::where('book_id', $reservation->book_id)
            ->where('is_available', true)
            ->whereNotIn('condition_status', ['damaged', 'lost'])
            ->first();

        if (! $copy) {
            return back()->withErrors(['reservation' => 'No issuable copy is available for this reservation.']);
        }

        $maxDays = (int) ($membership?->max_days_allowed ?? 14);
        BookIssue::create([
            'book_copy_id' => $copy->id,
            $borrowerColumn => $borrower->id,
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'due_date' => now()->addDays($maxDays)->toDateString(),
            'status' => 'issued',
        ]);
        $copy->update(['is_available' => false]);
        Book::where('id', $copy->book_id)->decrement('available_copies');
        $reservation->update(['status' => 'fulfilled']);

        return back()->with('success', 'Reservation fulfilled and book issued.');
    }

    public function cancelReservation(Request $r, LibraryReservation $reservation)
    {
        if ($reservation->status !== 'pending') {
            return back()->withErrors(['reservation' => 'Only pending reservations can be cancelled.']);
        }

        $reservation->update(['status' => 'cancelled']);

        return back()->with('success', 'Reservation cancelled.');
    }

    public function memberships(Request $r)
    {
        $memberships = LibraryMembership::with('user')->latest()->paginate(20);
        $users = User::orderBy('name')->get();
        return view('admin.library.memberships', compact('memberships', 'users'));
    }

    public function membershipStore(Request $r)
    {
        $data = $r->validate([
            'user_id'           => 'required|exists:users,id',
            'member_type'       => 'required|in:student,teacher,staff',
            'max_books_allowed' => 'required|integer|min:1|max:10',
            'max_days_allowed'  => 'required|integer|min:1|max:180',
            'fine_per_day'      => 'required|numeric|min:0',
            'expiry_date'       => 'nullable|date',
        ]);

        $activeIssueCount = $this->activeIssueCountForMembershipUser((int) $data['user_id'], $data['member_type']);
        if ((int) $data['max_books_allowed'] < $activeIssueCount) {
            return back()->withErrors([
                'max_books_allowed' => "Max books cannot be lower than the borrower's current active issue count ({$activeIssueCount}).",
            ])->withInput();
        }

        LibraryMembership::updateOrCreate(['user_id' => $data['user_id']], $data + ['is_active' => true]);
        return back()->with('success', 'Membership saved.');
    }

    public function fineCollection(Request $r)
    {
        $issues = BookIssue::where('fine_paid', false)->where('fine_amount', '>', 0)
            ->with(['bookCopy.book','student.user','teacher.user'])->latest()->paginate(20);
        return view('admin.library.fines', compact('issues'));
    }

    public function finePay(BookIssue $issue)
    {
        if (in_array($issue->status, ['issued', 'overdue'], true)) {
            return back()->withErrors(['fine' => 'Return the book before collecting the final fine.']);
        }

        if ((float) $issue->fine_amount <= 0) {
            return back()->withErrors(['fine' => 'There is no payable fine on this issue.']);
        }

        if ($issue->fine_paid) {
            return back()->withErrors(['fine' => 'This fine has already been paid.']);
        }

        $issue->update(['fine_paid' => true]);
        return back()->with('success', 'Fine of Rs. ' . number_format((float) $issue->fine_amount, 2) . ' marked as paid.');
    }

    private function expireStaleReservations(): void
    {
        LibraryReservation::where('status', 'pending')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }

    private function earliestPendingReservation(int $bookId): ?LibraryReservation
    {
        return LibraryReservation::where('book_id', $bookId)
            ->where('status', 'pending')
            ->orderBy('reserved_at')
            ->first();
    }

    private function matchingPendingReservation(int $bookId, string $borrowerType, int $borrowerId): ?LibraryReservation
    {
        $column = $borrowerType === 'student' ? 'student_id' : 'teacher_id';

        return LibraryReservation::where('book_id', $bookId)
            ->where($column, $borrowerId)
            ->where('status', 'pending')
            ->orderBy('reserved_at')
            ->first();
    }

    private function activeIssueCountForMembershipUser(int $userId, string $memberType): int
    {
        if ($memberType === 'student') {
            $studentId = Student::where('user_id', $userId)->value('id');

            return $studentId
                ? BookIssue::where('student_id', $studentId)->whereIn('status', ['issued', 'overdue'])->count()
                : 0;
        }

        if ($memberType === 'teacher') {
            $teacherId = Teacher::where('user_id', $userId)->value('id');

            return $teacherId
                ? BookIssue::where('teacher_id', $teacherId)->whereIn('status', ['issued', 'overdue'])->count()
                : 0;
        }

        return 0;
    }
}
