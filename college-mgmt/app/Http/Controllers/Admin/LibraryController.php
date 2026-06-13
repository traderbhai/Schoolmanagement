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
        $stats = [
            'total_books'    => Book::where('is_active', true)->count(),
            'total_copies'   => BookCopy::count(),
            'issued'         => BookIssue::whereIn('status', ['issued','overdue'])->count(),
            'overdue'        => BookIssue::where('status','overdue')->count(),
            'fines_pending'  => BookIssue::where('fine_paid', false)->where('fine_amount', '>', 0)->count(),
        ];
        $recentIssues = BookIssue::with(['bookCopy.book','student.user','teacher.user'])
            ->latest()->limit(10)->get();
        $students = Student::with('user')->where('status','active')->orderBy('id')->limit(200)->get();
        $books    = Book::where('is_active', true)->where('available_copies', '>', 0)->orderBy('title')->get();
        return view('admin.library.index', compact('stats', 'recentIssues', 'students', 'books'));
    }

    public function books(Request $r)
    {
        $query = Book::withCount(['copies','issues' => fn($q) => $q->whereIn('status',['issued','overdue'])]);
        if ($r->filled('q')) {
            $s = $r->q;
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
        $history = BookIssue::whereHas('bookCopy', fn($q) => $q->where('book_id', $book->id))
            ->with(['bookCopy','student.user','teacher.user'])->latest()->limit(50)->get();
        return view('admin.library.book-show', compact('book', 'history'));
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
        $r->validate([
            'book_id'    => 'required|exists:books,id',
            'student_id' => 'nullable|exists:students,id',
            'due_date'   => 'required|date|after:today',
        ]);
        $copy = BookCopy::where('book_id', $r->book_id)->where('is_available', true)->first();
        if (!$copy) return back()->with('error', 'No available copy for this book.');

        BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id'   => $r->student_id,
            'issued_by'    => auth()->id(),
            'issued_at'    => now(),
            'due_date'     => $r->due_date,
            'status'       => 'issued',
        ]);
        $copy->update(['is_available' => false]);
        Book::where('id', $r->book_id)->decrement('available_copies');
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
        return back()->with('success', $fine > 0 ? "Returned. Fine: ₹{$fine}" : 'Book returned successfully.');
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
        return back()->with('success', 'Fine of ₹' . $issue->fine_amount . ' marked as paid.');
    }
}
