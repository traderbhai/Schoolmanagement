@extends('layouts.student')
@section('title', 'My Library')
@section('page-title', 'Library')
@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold"><i class="bi bi-book me-2 text-primary"></i>Currently Borrowed</div>
            <div class="card-body p-0">
                @if($currentIssues->isEmpty())
                    <div class="text-center text-muted py-4">No books currently borrowed.</div>
                @else
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($currentIssues as $issue)
                        <tr>
                            <td><small>{{ $issue->bookCopy?->book?->title ?? '—' }}</small></td>
                            <td><small class="{{ $issue->is_overdue ? 'text-danger fw-semibold' : '' }}">{{ $issue->due_date?->format('d M Y') }}@if($issue->is_overdue)<br><span style="font-size:.7rem">{{ $issue->days_overdue }} days overdue</span>@endif</small></td>
                            <td><span class="badge bg-{{ $issue->status==='overdue'?'danger':'primary' }}">{{ ucfirst($issue->status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
    @if($fines->isNotEmpty())
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm border-danger">
            <div class="card-header bg-danger text-white fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Outstanding Fines</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Fine</th></tr></thead>
                    <tbody>@foreach($fines as $f)<tr><td><small>{{ $f->bookCopy?->book?->title ?? '—' }}</small></td><td><strong class="text-danger">₹{{ number_format($f->fine_amount,2) }}</strong></td></tr>@endforeach</tbody>
                </table>
                <div class="p-2 border-top"><small class="text-muted">Please pay at the library counter.</small></div>
            </div>
        </div>
    </div>
    @endif
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold"><i class="bi bi-bookmark-check me-2 text-primary"></i>My Reservations</div>
            <div class="card-body p-0">
                @if($reservations->isEmpty())
                    <div class="text-center text-muted py-4">No reservations yet.</div>
                @else
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Status</th><th>Expires</th><th></th></tr></thead>
                    <tbody>
                        @foreach($reservations as $reservation)
                        <tr>
                            <td><small>{{ $reservation->book?->title ?? 'Book removed' }}</small></td>
                            <td><span class="badge text-bg-{{ $reservation->status === 'pending' ? 'warning' : ($reservation->status === 'fulfilled' ? 'success' : 'secondary') }}">{{ ucfirst($reservation->status) }}</span></td>
                            <td><small>{{ $reservation->expires_at?->format('d M Y') }}</small></td>
                            <td class="text-end">
                                @if($reservation->status === 'pending')
                                    <form method="POST" action="{{ route('student.library.reservations.cancel', $reservation) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Catalog And Reservations</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Book</th><th>Author</th><th>Available</th><th>Queue</th><th></th></tr></thead>
                        <tbody>
                            @forelse($books as $book)
                            <tr>
                                <td><div class="fw-semibold">{{ $book->title }}</div><div class="small text-muted">{{ $book->isbn ?? 'No ISBN' }}</div></td>
                                <td>{{ $book->author }}</td>
                                <td><span class="badge text-bg-{{ $book->issuable_copies_count > 0 ? 'success' : 'secondary' }}">{{ $book->issuable_copies_count }}</span></td>
                                <td>{{ $book->pending_reservations_count }}</td>
                                <td class="text-end">
                                    @if($book->issuable_copies_count > 0)
                                        <span class="text-muted small">Available at counter</span>
                                    @else
                                        <form method="POST" action="{{ route('student.library.reservations.store') }}">
                                            @csrf
                                            <input type="hidden" name="book_id" value="{{ $book->id }}">
                                            <button class="btn btn-sm btn-outline-primary">Reserve</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">No catalog books available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold">Borrowing History</div>
            <div class="card-body p-0">
                @if($history->isEmpty())
                    <div class="text-center text-muted py-3">No borrowing history.</div>
                @else
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Book</th><th>Issued</th><th>Returned</th><th>Fine</th></tr></thead>
                    <tbody>
                        @foreach($history as $h)
                        <tr>
                            <td><small>{{ $h->bookCopy?->book?->title ?? '—' }}</small></td>
                            <td><small>{{ $h->issued_at?->format('d M Y') }}</small></td>
                            <td><small>{{ $h->returned_at?->format('d M Y') ?? '—' }}</small></td>
                            <td><small>{{ $h->fine_amount > 0 ? '₹'.$h->fine_amount : '—' }}</small></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
