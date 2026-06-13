<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Book, BookCopy, BookIssue, LibraryMembership, Student, Teacher, User};
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
        $data = $r->validate([
            'book_copy_id'  => 'nullable|exists:book_copies,id',
            'book_id'       => 'nullable|exists:books,id',
            'borrower_type' => 'required|in:student,teacher',
            'borrower_id'   => 'required|integer',
            'due_date'      => 'required|date|after:today',
        ]);

        if (empty($data['book_copy_id']) && empty($data['book_id'])) {
            return back()->withErrors(['book_copy_id' => 'Select a book copy or book to issue.']);
        }

        $borrowerColumn = $data['borrower_type'] === 'student' ? 'student_id' : 'teacher_id';
        $borrowerClass = $data['borrower_type'] === 'student' ? Student::class : Teacher::class;
        $borrower = $borrowerClass::find($data['borrower_id']);
        if (! $borrower) {
            return back()->withErrors(['borrower_id' => 'Selected borrower was not found.']);
        }

        $membership = LibraryMembership::where('user_id', $borrower->user_id)->where('is_active', true)->first();
        if ($membership && $membership->expiry_date && $membership->expiry_date->isPast()) {
            return back()->withErrors(['borrower_id' => 'Library membership has expired.']);
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
        return back()->with('success', 'Book issued successfully.');
    }

    public function returnBook(BookIssue $issue)
    {
        if ($issue->returned_at) return back()->with('error', 'Book already returned.');
        $fine = $this->fines->calculateFine($issue);
        $issue->update([
            'returned_at'         => now(),
            'return_accepted_by'  => auth()->id(),
            'fine_amount'         => $fine,
            'status'              => 'returned',
        ]);
        $issue->bookCopy->update(['is_available' => true]);
        Book::where('id', $issue->bookCopy->book_id)->increment('available_copies');
        return back()->with('success', $fine > 0 ? 'Returned. Fine: Rs. ' . number_format($fine, 2) : 'Book returned successfully.');
    }

    public function issues(Request $r)
    {
        $query = BookIssue::with(['bookCopy.book','student.user','teacher.user']);
        if ($r->filled('status')) $query->where('status', $r->status);
        $issues = $query->latest()->paginate(20)->withQueryString();
        return view('admin.library.issues', compact('issues'));
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
        $issue->update(['fine_paid' => true]);
        return back()->with('success', 'Fine of Rs. ' . number_format((float) $issue->fine_amount, 2) . ' marked as paid.');
    }
}
