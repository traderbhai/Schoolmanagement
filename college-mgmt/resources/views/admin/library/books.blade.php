@extends('layouts.admin')
@section('title', 'Books Catalog')
@section('page-title', 'Books Catalog')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.library.index') }}">Library</a></li>
    <li class="breadcrumb-item active">Books</li>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">{{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title, author, ISBN..." value="{{ request('search') }}" style="width:280px">
        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
        @if(request('search'))<a href="{{ route('admin.library.books') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a>@endif
    </form>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBookModal"><i class="bi bi-plus-circle me-1"></i>Add Book</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Copies</th>
                        <th>Available</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($books as $book)
                    <tr>
                        <td><a href="{{ route('admin.library.books.show', $book) }}">{{ $book->title }}</a></td>
                        <td>{{ $book->author }}</td>
                        <td><small class="text-muted">{{ $book->isbn ?? '-' }}</small></td>
                        <td>{{ $book->category ?? '-' }}</td>
                        <td>{{ $book->copies_count }}</td>
                        <td>
                            <span class="badge {{ $book->active_copies_count > 0 ? 'bg-success' : 'bg-danger' }}">
                                {{ $book->active_copies_count }}
                            </span>
                        </td>
                        <td>
                            @if($book->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.library.books.show', $book) }}" class="btn btn-xs btn-outline-primary btn-sm"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No books found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($books->hasPages())
    <div class="card-footer">{{ $books->links() }}</div>
    @endif
</div>

{{-- Add Book Modal --}}
<div class="modal fade" id="addBookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.library.books.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-book me-2"></i>Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="300">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">ISBN</label>
                            <input type="text" name="isbn" class="form-control" maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Author <span class="text-danger">*</span></label>
                            <input type="text" name="author" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Publisher</label>
                            <input type="text" name="publisher" class="form-control" maxlength="200">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Edition</label>
                            <input type="text" name="edition" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Year</label>
                            <input type="number" name="year_of_publication" class="form-control" min="1000" max="{{ date('Y')+1 }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" maxlength="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Language</label>
                            <input type="text" name="language" class="form-control" value="English" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Number of Copies</label>
                            <input type="number" name="total_copies" class="form-control" value="1" min="1" max="999">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Shelf Location</label>
                            <input type="text" name="location" class="form-control" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Add Book</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
