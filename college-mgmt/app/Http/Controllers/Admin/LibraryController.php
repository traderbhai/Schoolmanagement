<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\LibraryMembership;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LibraryFineService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LibraryController extends Controller {
    public function index() {
        $totalBooks = Book::where('is_active', true)->count();
        $totalCopies = BookCopy::count();
        $issuedToday = BookIssue::whereDate('issued_at', today())->count();
        $overdueCount = BookIssue::where('status', 'overdue')->count();
        $dueToday = BookIssue::whereDate('due_date', today())->where('status', 'issued')->count();
        $finesPending = BookIssue::where('fine_paid', false)->where('fine_amount', '>', 0)->count();
        $latestIssues = BookIssue::with(['bookCopy.book','student','teacher'])
            ->latest()->take(10)->get();
        return view('admin.library.index', compact(
            'totalBooks','totalCopies','issuedToday','overdueCount','dueToday','finesPending','latestIssues'
        ));
    }

    public function books(Request $r) {
        $q = Book::query();
        if ($r->filled('search')) {
            $search = $r->search;
            $q->where(function($query) use ($search) {
                $query->where('title','like',"%{$search}%")
                    ->orWhere('author','like',"%{$search}%")
                    ->orWhere('isbn','like',"%{$search}%");
            });
        }
        $books = $q->withCount(['copies','activeCopies'])->latest()->paginate(20)->withQueryString();
        return view('admin.library.books', compact('books'));
    }

    public function bookStore(Request $r) {
        $validated = $r->validate([
            'isbn' => 'nullable|string|max:20|unique:books,isbn',
            'title' => 'required|string|max:300',
            'author' => 'required|string|max:200',
            'publisher' => 'nullable|string|max:200',
            'edition' => 'nullable|string|max:50',
            'year_of_publication' => 'nullable|integer|min:1000|max:' . (date('Y')+1),
            'category' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'total_copies' => 'nullable|integer|min:1|max:999',
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);
        $totalCopies = (int) ($validated['total_copies'] ?? 1);
        $validated['total_copies'] = $totalCopies;
        $validated['available_copies'] = $totalCopies;
        $book = Book::create($validated);
        $year = date('Y');
        $lastCopy = BookCopy::where('accession_number', 'like', "ACC-{$year}-%")
            ->orderByRaw("CAST(SUBSTR(accession_number, 10) AS INTEGER) DESC")
            ->first();
        $nextNum = $lastCopy ? ((int) substr($lastCopy->accession_number, 9)) + 1 : 1;
        for ($i = 0; $i < $totalCopies; $i++) {
            BookCopy::create([
                'book_id' => $book->id,
                'accession_number' => 'ACC-' . $year . '-' . str_pad($nextNum + $i, 5, '0', STR_PAD_LEFT),
                'condition_status' => 'good',
                'is_available' => true,
            ]);
        }
        return redirect()->route('admin.library.books')->with('success', 'Book added successfully.');
    }

    public function bookShow(Book $book) {
        $copies = $book->copies()->with('currentIssue.student','currentIssue.teacher')->get();
        $issueHistory = BookIssue::whereHas('bookCopy', fn($q) => $q->where('book_id', $book->id))
            ->with(['bookCopy','student','teacher'])
            ->latest('issued_at')->take(50)->get();
        return view('admin.library.book-show', compact('book','copies','issueHistory'));
    }

    public function bookUpdate(Request $r, Book $book) {
        $validated = $r->validate([
            'isbn' => 'nullable|string|max:20|unique:books,isbn,' . $book->id,
            'title' => 'required|string|max:300',
            'author' => 'required|string|max:200',
            'publisher' => 'nullable|string|max:200',
            'edition' => 'nullable|string|max:50',
            'year_of_publication' => 'nullable|integer|min:1000|max:' . (date('Y')+1),
            'category' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $book->update($validated);
        return redirect()->route('admin.library.books.show', $book)->with('success', 'Book updated.');
    }

    public function issueBook(Request $r) {
        $r->validate([
            'book_copy_id' => 'required|exists:book_copies,id',
            'borrower_type' => 'required|in:student,teacher',
            'borrower_id' => 'required|integer',
            'due_date' => 'required|date|after:today',
        ]);
        $copy = BookCopy::findOrFail($r->book_copy_id);
        if (!$copy->is_available) {
            return back()->withErrors(['book_copy_id' => 'This copy is not available.']);
        }
        $userId = null;
        $studentId = null;
        $teacherId = null;
        if ($r->borrower_type === 'student') {
            $student = Student::findOrFail($r->borrower_id);
            $studentId = $student->id;
            $userId = $student->user_id;
        } else {
            $teacher = Teacher::findOrFail($r->borrower_id);
            $teacherId = $teacher->id;
            $userId = $teacher->user_id;
        }
        $membership = LibraryMembership::where('user_id', $userId)->where('is_active', true)->first();
        if ($membership) {
            $currentIssues = BookIssue::where($r->borrower_type . '_id', $r->borrower_id)
                ->whereIn('status', ['issued','overdue'])->count();
            if ($currentIssues >= $membership->max_books_allowed) {
                return back()->withErrors(['borrower_id' => 'Borrower has reached maximum book limit.']);
            }
        }
        BookIssue::create([
            'book_copy_id' => $copy->id,
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'due_date' => $r->due_date,
            'status' => 'issued',
        ]);
        $copy->update(['is_available' => false]);
        $copy->book->decrement('available_copies');
        return redirect()->route('admin.library.issues')->with('success', 'Book issued successfully.');
    }

    public function returnBook(BookIssue $issue) {
        $fineService = new LibraryFineService();
        $fine = $fineService->calculateFine($issue);
        $issue->update([
            'returned_at' => now(),
            'return_accepted_by' => auth()->id(),
            'fine_amount' => $fine,
            'status' => 'returned',
        ]);
        $issue->bookCopy->update(['is_available' => true]);
        $issue->bookCopy->book->increment('available_copies');
        return redirect()->route('admin.library.issues')->with('success', 'Book returned successfully.');
    }

    public function issues(Request $r) {
        $q = BookIssue::with(['bookCopy.book','student','teacher']);
        if ($r->filled('status') && $r->status !== 'all') {
            $q->where('status', $r->status);
        }
        $issues = $q->latest()->paginate(20)->withQueryString();
        return view('admin.library.issues', compact('issues'));
    }

    public function memberships(Request $r) {
        $memberships = LibraryMembership::with('user')->latest()->paginate(20);
        $users = User::orderBy('name')->get();
        return view('admin.library.memberships', compact('memberships','users'));
    }

    public function membershipStore(Request $r) {
        $r->validate([
            'user_id' => 'required|exists:users,id',
            'member_type' => 'required|in:student,teacher,staff',
            'max_books_allowed' => 'required|integer|min:1|max:20',
            'max_days_allowed' => 'required|integer|min:1|max:365',
            'fine_per_day' => 'required|numeric|min:0|max:100',
            'expiry_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);
        LibraryMembership::updateOrCreate(
            ['user_id' => $r->user_id],
            $r->only(['member_type','max_books_allowed','max_days_allowed','fine_per_day','expiry_date','is_active'])
        );
        return redirect()->route('admin.library.memberships')->with('success', 'Membership saved.');
    }

    public function fineCollection(Request $r) {
        $fines = BookIssue::with(['bookCopy.book','student','teacher'])
            ->where('fine_paid', false)
            ->where('fine_amount', '>', 0)
            ->latest()->paginate(20);
        return view('admin.library.fines', compact('fines'));
    }

    public function finePay(BookIssue $issue) {
        $issue->update(['fine_paid' => true]);
        return redirect()->route('admin.library.fines')->with('success', 'Fine marked as paid.');
    }
}
