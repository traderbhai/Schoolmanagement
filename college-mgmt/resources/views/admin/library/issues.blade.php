@extends('layouts.admin')
@section('title', 'Book Issues')
@section('page-title', 'Book Issues')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.library.index') }}">Library</a></li>
    <li class="breadcrumb-item active">Issues</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters + Issue Button --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group" role="group">
        <a href="{{ route('admin.library.issues') }}" class="btn btn-sm {{ !request('status') || request('status') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
        <a href="{{ route('admin.library.issues') }}?status=issued" class="btn btn-sm {{ request('status') === 'issued' ? 'btn-primary' : 'btn-outline-primary' }}">Issued</a>
        <a href="{{ route('admin.library.issues') }}?status=overdue" class="btn btn-sm {{ request('status') === 'overdue' ? 'btn-danger' : 'btn-outline-danger' }}">Overdue</a>
        <a href="{{ route('admin.library.issues') }}?status=returned" class="btn btn-sm {{ request('status') === 'returned' ? 'btn-success' : 'btn-outline-success' }}">Returned</a>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#issueBookModal"><i class="bi bi-plus-circle me-1"></i>Issue Book</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Book</th>
                        <th>Accession</th>
                        <th>Borrower</th>
                        <th>Issued</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Fine</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issues as $issue)
                    <tr class="{{ $issue->status === 'overdue' ? 'table-danger' : '' }}">
                        <td>
                            <a href="{{ route('admin.library.books.show', $issue->bookCopy->book ?? 0) }}">
                                {{ $issue->bookCopy->book->title ?? 'N/A' }}
                            </a>
                        </td>
                        <td><code>{{ $issue->bookCopy->accession_number ?? '' }}</code></td>
                        <td>
                            @if($issue->student)
                                <span class="badge bg-primary">S</span> {{ $issue->student->user->name ?? '' }}
                            @elseif($issue->teacher)
                                <span class="badge bg-success">T</span> {{ $issue->teacher->user->name ?? '' }}
                            @else N/A
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($issue->issued_at)->format('d M Y') }}</td>
                        <td>{{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}</td>
                        <td>
                            @if($issue->status === 'returned')
                                <span class="badge bg-success">Returned</span>
                            @elseif($issue->status === 'overdue')
                                <span class="badge bg-danger">Overdue</span>
                            @elseif($issue->status === 'issued')
                                <span class="badge bg-primary">Issued</span>
                            @else
                                <span class="badge bg-secondary">{{ $issue->status }}</span>
                            @endif
                        </td>
                        <td>{{ $issue->fine_amount > 0 ? '₹'.$issue->fine_amount : '-' }}</td>
                        <td>
                            @if(in_array($issue->status, ['issued','overdue']))
                                <form method="POST" action="{{ route('admin.library.return', $issue) }}" class="d-inline" onsubmit="return confirm('Mark this book as returned?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" title="Return"><i class="bi bi-arrow-return-left"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No issues found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($issues->hasPages())
    <div class="card-footer">{{ $issues->links() }}</div>
    @endif
</div>

{{-- Issue Book Modal --}}
<div class="modal fade" id="issueBookModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.library.issue') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-up-right-square me-2"></i>Issue Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Book Copy Accession Number *</label>
                        <input type="number" name="book_copy_id" class="form-control" placeholder="Enter book copy ID" required>
                        <div class="form-text">Enter the book copy ID (numeric). You can find it on the book detail page.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Borrower Type *</label>
                        <select name="borrower_type" class="form-select" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Borrower ID *</label>
                        <input type="number" name="borrower_id" class="form-control" placeholder="Student or Teacher ID" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Due Date *</label>
                        <input type="date" name="due_date" class="form-control" required min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Issue Book</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
