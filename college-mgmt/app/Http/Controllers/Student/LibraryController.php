<?php
namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\LibraryMembership;
use App\Models\LibraryReservation;
use App\Models\Student;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function index()
    {
        $this->expireStaleReservations();

        $student = Student::where('user_id', auth()->id())->firstOrFail();
        $currentIssues = BookIssue::where('student_id', $student->id)
            ->whereIn('status', ['issued','overdue'])
            ->with('bookCopy.book')
            ->get();
        $fines = BookIssue::where('student_id', $student->id)
            ->where('fine_paid', false)
            ->where('fine_amount', '>', 0)
            ->with('bookCopy.book')
            ->get();
        $history = BookIssue::where('student_id', $student->id)
            ->where('status', 'returned')
            ->with('bookCopy.book')
            ->latest()
            ->limit(10)
            ->get();
        $reservations = LibraryReservation::where('student_id', $student->id)
            ->with('book')
            ->latest('reserved_at')
            ->limit(20)
            ->get();
        $books = Book::where('is_active', true)
            ->withCount([
                'availableCopies as issuable_copies_count' => fn ($q) => $q->whereNotIn('condition_status', ['damaged', 'lost']),
                'reservations as pending_reservations_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->orderBy('title')
            ->limit(50)
            ->get();

        return view('student.library.index', compact('currentIssues', 'fines', 'history', 'reservations', 'books'));
    }

    public function reserve(Request $request)
    {
        $this->expireStaleReservations();

        $data = $request->validate([
            'book_id' => 'required|exists:books,id',
        ]);

        $student = Student::where('user_id', auth()->id())->firstOrFail();

        if ($student->status !== 'active') {
            return back()->withErrors(['book_id' => 'Library reservations are available only for active students.']);
        }

        $book = Book::withCount([
            'availableCopies as issuable_copies_count' => fn ($q) => $q->whereNotIn('condition_status', ['damaged', 'lost']),
        ])->findOrFail($data['book_id']);

        if (! $book->is_active) {
            return back()->withErrors(['book_id' => 'This book is not currently active for circulation.']);
        }

        if ($book->issuable_copies_count > 0) {
            return back()->withErrors(['book_id' => 'This book has an available copy. Please collect it through the library counter.']);
        }

        $hasActiveIssueForTitle = BookIssue::where('student_id', $student->id)
            ->whereIn('status', ['issued', 'overdue'])
            ->whereHas('bookCopy', fn ($copy) => $copy->where('book_id', $book->id))
            ->exists();

        if ($hasActiveIssueForTitle) {
            return back()->withErrors(['book_id' => 'You already have this title issued. Return the current copy before placing a reservation.']);
        }

        $membership = LibraryMembership::where('user_id', $student->user_id)->where('is_active', true)->first();
        if ($membership && $membership->expiry_date && $membership->expiry_date->isPast()) {
            return back()->withErrors(['book_id' => 'Your library membership has expired.']);
        }

        $alreadyReserved = LibraryReservation::where('book_id', $book->id)
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyReserved) {
            return back()->withErrors(['book_id' => 'You already have a pending reservation for this book.']);
        }

        LibraryReservation::create([
            'book_id' => $book->id,
            'student_id' => $student->id,
            'reserved_at' => now(),
            'expires_at' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Book reservation added.');
    }

    public function cancelReservation(LibraryReservation $reservation)
    {
        $this->expireStaleReservations();
        $reservation->refresh();

        $student = Student::where('user_id', auth()->id())->firstOrFail();

        if ((int) $reservation->student_id !== (int) $student->id) {
            abort(403);
        }

        if ($reservation->status !== 'pending') {
            return back()->withErrors(['reservation' => 'Only pending reservations can be cancelled.']);
        }

        $reservation->update(['status' => 'cancelled']);

        return back()->with('success', 'Reservation cancelled.');
    }

    private function expireStaleReservations(): void
    {
        LibraryReservation::where('status', 'pending')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->update(['status' => 'expired']);
    }
}
