@extends('layouts.admin')
@section('title', $book->title)
@section('page-title', 'Book Detail')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.library.index') }}">Library</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.library.books') }}">Books</a></li>
    <li class="breadcrumb-item active">{{ Str::limit($book->title, 40) }}</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-4">
    {{-- Book Info --}}
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">{{ $book->title }}</h5>
                <p class="text-muted mb-1">by <strong>{{ $book->author }}</strong></p>
                @if($book->publisher)<p class="mb-1 small"><i class="bi bi-building me-1"></i>{{ $book->publisher }}</p>@endif
                @if($book->isbn)<p class="mb-1 small"><i class="bi bi-upc me-1"></i>ISBN: {{ $book->isbn }}</p>@endif
                @if($book->edition)<p class="mb-1 small"><i class="bi bi-layers me-1"></i>{{ $book->edition }} Edition</p>@endif
                @if($book->year_of_publication)<p class="mb-1 small"><i class="bi bi-calendar me-1"></i>{{ $book->year_of_publication }}</p>@endif
                @if($book->category)<p class="mb-1 small"><i class="bi bi-tag me-1"></i>{{ $book->category }}</p>@endif
                @if($book->location)<p class="mb-1 small"><i class="bi bi-geo-alt me-1"></i>{{ $book->location }}</p>@endif
                <hr>
                <div class="d-flex justify-content-between">
                    <div class="text-center"><div class="fw-bold text-primary fs-5">{{ $book->total_copies }}</div><small class="text-muted">Total</small></div>
                    <div class="text-center"><div class="fw-bold text-success fs-5">{{ $book->available_copies }}</div><small class="text-muted">Available</small></div>
                    <div class="text-center"><div class="fw-bold text-danger fs-5">{{ $book->total_copies - $book->available_copies }}</div><small class="text-muted">Issued</small></div>
                </div>
                @if($book->description)<hr><p class="small text-muted">{{ $book->description }}</p>@endif
            </div>
            <div class="card-footer">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editBookModal"><i class="bi bi-pencil me-1"></i>Edit</button>
            </div>
        </div>
    </div>

    {{-- Copies --}}
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-collection me-2"></i>Copies ({{ $copies->count() }})</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Accession No.</th><th>Condition</th><th>Status</th><th>Borrower</th></tr>
                        </thead>
                        <tbody>
                            @foreach($copies as $copy)
                            <tr>
                                <td><code>{{ $copy->accession_number }}</code></td>
                                <td>{{ ucfirst($copy->condition_status) }}</td>
                                <td>
                                    @if($copy->is_available)
                                        <span class="badge bg-success">Available</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Issued</span>
                                    @endif
                                </td>
                                <td>
                                    @if($copy->currentIssue)
                                        @if($copy->currentIssue->student)
                                            {{ $copy->currentIssue->student->user->name ?? '' }}
                                            <small class="text-muted">(Due: {{ \Carbon\Carbon::parse($copy->currentIssue->due_date)->format('d M') }})</small>
                                        @elseif($copy->currentIssue->teacher)
                                            {{ $copy->currentIssue->teacher->user->name ?? '' }}
                                            <small class="text-muted">(Due: {{ \Carbon\Carbon::parse($copy->currentIssue->due_date)->format('d M') }})</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Issue History --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Issue History</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Accession</th><th>Borrower</th><th>Issued</th><th>Due</th><th>Returned</th><th>Fine</th></tr>
                        </thead>
                        <tbody>
                            @forelse($issueHistory as $issue)
                            <tr>
                                <td><code>{{ $issue->bookCopy->accession_number ?? '' }}</code></td>
                                <td>
                                    @if($issue->student) {{ $issue->student->user->name ?? '' }}
                                    @elseif($issue->teacher) {{ $issue->teacher->user->name ?? '' }}
                                    @endif
                                </td>
                                <td>{{ \Carbon\Carbon::parse($issue->issued_at)->format('d M Y') }}</td>
                                <td>{{ \Carbon\Carbon::parse($issue->due_date)->format('d M Y') }}</td>
                                <td>{{ $issue->returned_at ? \Carbon\Carbon::parse($issue->returned_at)->format('d M Y') : '-' }}</td>
                                <td>{{ $issue->fine_amount > 0 ? '₹'.$issue->fine_amount : '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No issue history.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Book Modal --}}
<div class="modal fade" id="editBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.library.books.update', $book) }}">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title *</label>
                            <input type="text" name="title" class="form-control" value="{{ $book->title }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ISBN</label>
                            <input type="text" name="isbn" class="form-control" value="{{ $book->isbn }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Author *</label>
                            <input type="text" name="author" class="form-control" value="{{ $book->author }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Publisher</label>
                            <input type="text" name="publisher" class="form-control" value="{{ $book->publisher }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Edition</label>
                            <input type="text" name="edition" class="form-control" value="{{ $book->edition }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Year</label>
                            <input type="number" name="year_of_publication" class="form-control" value="{{ $book->year_of_publication }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" value="{{ $book->category }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Language</label>
                            <input type="text" name="language" class="form-control" value="{{ $book->language }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Location</label>
                            <input type="text" name="location" class="form-control" value="{{ $book->location }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1" {{ $book->is_active ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ !$book->is_active ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ $book->description }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
