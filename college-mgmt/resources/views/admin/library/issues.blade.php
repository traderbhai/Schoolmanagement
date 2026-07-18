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
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button aria-label="Close alert" type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Filters + Issue Button --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group" role="group">
        <a href="{{ route('admin.library.issues') }}" class="btn btn-sm {{ !request('status') || request('status') === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">All issues</a>
        <a href="{{ route('admin.library.issues') }}?status=issued" class="btn btn-sm {{ request('status') === 'issued' ? 'btn-primary' : 'btn-outline-primary' }}">Issued</a>
        <a href="{{ route('admin.library.issues') }}?status=overdue" class="btn btn-sm {{ request('status') === 'overdue' ? 'btn-danger' : 'btn-outline-danger' }}">Overdue</a>
        <a href="{{ route('admin.library.issues') }}?status=returned" class="btn btn-sm {{ request('status') === 'returned' ? 'btn-success' : 'btn-outline-success' }}">Returned</a>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.library.issues.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export Current View</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#issueBookModal"><i class="bi bi-plus-circle me-1"></i>Issue Book</button>
    </div>
</div>
<div class="text-muted small mb-2">Showing {{ $issues->total() }} issue record(s){{ request('status') ? ' filtered by status: '.request('status') : '' }}.</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Book</th>
                        <th scope="col">Accession</th>
                        <th scope="col">Borrower</th>
                        <th scope="col">Issued</th>
                        <th scope="col">Due Date</th>
                        <th scope="col">Status</th>
                        <th scope="col">Fine</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issues as $issue)
                    <tr class="{{ $issue->status === 'overdue' ? 'table-danger' : '' }}">
                        <td>
                            <a href="{{ route('admin.library.books.show', $issue->bookCopy->book ?? 0) }}">
                                {{ $issue->bookCopy->book->title ?? 'Book title missing' }}
                            </a>
                        </td>
                        <td><code>{{ $issue->bookCopy->accession_number ?? 'Accession pending' }}</code></td>
                        <td>
                            @if($issue->student)
                                <span class="badge bg-primary">S</span> {{ $issue->student->user->name ?? '' }}
                            @elseif($issue->teacher)
                                <span class="badge bg-success">T</span> {{ $issue->teacher->user->name ?? '' }}
                            @else
                                <span class="text-muted">Borrower not linked</span>
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
                        <td>{{ $issue->fine_amount > 0 ? 'Rs. '.number_format((float) $issue->fine_amount, 2) : 'No fine' }}</td>
                        <td>
                            @if(in_array($issue->status, ['issued','overdue']))
                                <form method="POST" action="{{ route('admin.library.issues.return', $issue) }}" class="d-inline" onsubmit="return confirm('Mark {{ addslashes($issue->bookCopy->book->title ?? 'this book') }} as returned? Confirm copy condition, borrower, due/fine status, and shelf availability before closing the issue.')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" title="Return" aria-label="Mark {{ $issue->bookCopy->book->title ?? 'book' }} as returned"><i class="bi bi-arrow-return-left"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">
                            No issue records match this view. Clear filters or issue an available copy to an active student, teacher, or staff member.
                        </td>
                    </tr>
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
                    <button aria-label="Close dialog" type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Available Copy *</label>
                        <select aria-label="Book Copy" name="book_copy_id" class="form-select" required>
                            <option value="">Select available copy</option>
                            @foreach($availableCopies as $copy)
                                <option value="{{ $copy->id }}">
                                    {{ $copy->book?->title ?? 'Untitled book' }} - {{ $copy->accession_number }}
                                    @if($copy->book?->author) ({{ $copy->book->author }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Only currently available copies are listed.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Borrower *</label>
                        <select aria-label="Borrower Key" name="borrower_key" class="form-select" required>
                            <option value="">Select borrower</option>
                            <optgroup label="Students">
                                @foreach($students as $student)
                                    <option value="student:{{ $student->id }}">
                                        {{ $student->user?->name ?? 'Student' }} - {{ $student->roll_number ?? $student->enrollment_number ?? 'No roll number' }}
                                    </option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Teachers">
                                @foreach($teachers as $teacher)
                                    <option value="teacher:{{ $teacher->id }}">
                                        {{ $teacher->user?->name ?? 'Teacher' }} - {{ $teacher->employee_id }}
                                    </option>
                                @endforeach
                            </optgroup>
                        </select>
                        <div class="form-text">Membership and active issue limits are checked before issuing.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Due Date *</label>
                        <input aria-label="Due Date" type="date" name="due_date" class="form-control" required min="{{ date('Y-m-d', strtotime('+1 day')) }}">
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
